<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * Gestion de la licence du plugin glpiaichat
 *
 * Mode "stub" :
 * - Clé spéciale "BREST EN VUE" : licence perpétuelle sans expiration.
 * - Toute autre clé non vide et de longueur suffisante est considérée comme valide 365 jours.
 */
class PluginGlpiaichatLicense {

   private const CONFIG_SECTION = 'glpiaichat';

   /**
    * Charge la configuration "licence" dans la table Config GLPI
    */
   public static function loadConfig(): array {
      return Config::getConfigurationValues(self::CONFIG_SECTION, [
         'license_key',
         'license_status',
         'license_expires_at',
         'license_last_check',
         'license_message',
      ]);
   }

   /**
    * Sauvegarde la configuration "licence" dans Config GLPI
    */
   private static function saveConfig(array $data): void {
      $allowed = [
         'license_key',
         'license_status',
         'license_expires_at',
         'license_last_check',
         'license_message',
      ];

      $toSave = [];
      foreach ($allowed as $k) {
         if (array_key_exists($k, $data)) {
            $toSave[$k] = $data[$k];
         }
      }

      if (!empty($toSave)) {
         Config::setConfigurationValues(self::CONFIG_SECTION, $toSave);
      }
   }

   /**
    * Indique si la licence est actuellement valide (en tenant compte de la date d'expiration)
    */
   public static function isValid(): bool {
      $conf = self::loadConfig();

      $status = $conf['license_status'] ?? 'none';
      $key    = trim((string)($conf['license_key'] ?? ''));

      if ($status !== 'valid' || $key === '') {
         return false;
      }

      // Vérifier la date d'expiration si présente
      $exp = trim((string)($conf['license_expires_at'] ?? ''));
      if ($exp !== '') {
         $ts = strtotime($exp . ' 23:59:59');
         if ($ts !== false && $ts < time()) {
            return false;
         }
      }

      return true;
   }

   /**
    * Retourne l'état détaillé de la licence pour affichage
    */
   public static function getStatus(): array {
      $conf  = self::loadConfig();
      $valid = self::isValid();

      return [
         'valid'           => $valid,
         'license_key'     => trim((string)($conf['license_key'] ?? '')),
         'status'          => $conf['license_status'] ?? 'none',
         'expires_at'      => $conf['license_expires_at'] ?? '',
         'last_check'      => $conf['license_last_check'] ?? '',
         'message'         => $conf['license_message'] ?? '',
      ];
   }

   /**
    * Active ou met à jour la licence à partir d'une clé saisie par l'administrateur.
    *
    * Mode stub :
    * - Clé vide      => invalid
    * - Clé spéciale  => valide sans expiration
    * - Clé < 10 char => invalid
    * - Sinon         => valid pour 365 jours
    *
    * Retour :
    * [
    *   'success'  => bool,
    *   'error'    => string|null,
    *   'status'   => 'valid'|'invalid'|...,
    *   'message'  => string,
    *   'expires_at' => 'YYYY-MM-DD'|null
    * ]
    */
   public static function activate(string $key): array {
      $key = trim($key);

      if ($key === '') {
         self::saveConfig([
            'license_key'        => '',
            'license_status'     => 'invalid',
            'license_expires_at' => '',
            'license_last_check' => date('Y-m-d H:i:s'),
            'license_message'    => 'Clé de licence vide.',
         ]);

         return [
            'success'    => false,
            'error'      => 'empty_key',
            'status'     => 'invalid',
            'message'    => 'Clé de licence vide.',
            'expires_at' => null,
         ];
      }

      // ----- Clé spéciale : "BREST EN VUE" => licence perpétuelle, sans expiration -----
      if ($key === 'BREST EN VUE') {
         $msg = "Licence perpétuelle active (clé spéciale BREST EN VUE).";

         self::saveConfig([
            'license_key'        => $key,
            'license_status'     => 'valid',
            'license_expires_at' => '', // pas de date => jamais expirée
            'license_last_check' => date('Y-m-d H:i:s'),
            'license_message'    => $msg,
         ]);

         return [
            'success'    => true,
            'error'      => null,
            'status'     => 'valid',
            'message'    => $msg,
            'expires_at' => null,
         ];
      }

      // Règle de validation très simple pour les autres clés
      if (mb_strlen($key, 'UTF-8') < 10) {
         self::saveConfig([
            'license_key'        => $key,
            'license_status'     => 'invalid',
            'license_expires_at' => '',
            'license_last_check' => date('Y-m-d H:i:s'),
            'license_message'    => 'Clé de licence invalide.',
         ]);

         return [
            'success'    => false,
            'error'      => 'too_short',
            'status'     => 'invalid',
            'message'    => 'Clé de licence invalide.',
            'expires_at' => null,
         ];
      }

      // MODE DEV / STUB : autres clés valides 365 jours
      $expiresAt = date('Y-m-d', strtotime('+365 days'));
      $msg       = "Licence de test valide jusqu'au " . date('d/m/Y', strtotime($expiresAt)) . '.';

      self::saveConfig([
         'license_key'        => $key,
         'license_status'     => 'valid',
         'license_expires_at' => $expiresAt,
         'license_last_check' => date('Y-m-d H:i:s'),
         'license_message'    => $msg,
      ]);

      return [
         'success'    => true,
         'error'      => null,
         'status'     => 'valid',
         'message'    => $msg,
         'expires_at' => $expiresAt,
      ];
   }
}
