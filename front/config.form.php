<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$saved            = false;
$activation_error = '';

// ----- Gestion activation licence (popup) -----
if (isset($_POST['activate_license'])) {
   // On essaie d'activer la licence via la classe stub
   $result = PluginGlpiaichatLicense::activate($_POST['license_key'] ?? '');
   if (!$result['success']) {
      $activation_error = $result['message'] ?? 'Erreur lors de l’activation de la licence.';
   } else {
      // En cas de succès, on laisse la page se recharger avec une licence valide
      $activation_error = '';
   }
}

// Charger l'état actuel de la licence
$license = PluginGlpiaichatLicense::getStatus();
$has_valid_license = $license['valid'];

// ----- Sauvegarde de la config du plugin (seulement si licence valide) -----
if ($has_valid_license && isset($_POST['update'])) {
   Config::setConfigurationValues('glpiaichat', [
      'support_phone' => $_POST['support_phone'] ?? '',
      'ai_api_url'    => $_POST['ai_api_url'] ?? '',
      'ai_api_key'    => $_POST['ai_api_key'] ?? '',
      'system_prompt' => $_POST['system_prompt'] ?? '',
   ]);

   $saved = true;
}

// Charger la config actuelle du plugin
$config = Config::getConfigurationValues('glpiaichat', [
   'support_phone',
   'ai_api_url',
   'ai_api_key',
   'system_prompt',
   'license_key',
   'license_status',
   'license_expires_at',
   'license_message',
]);

Html::header(
   'Configuration du chatbot IA',
   $_SERVER['PHP_SELF'],
   'config',
   'plugins'
);

// ==========================================================================
// 1) Si la licence n'est PAS valide : on affiche UNIQUEMENT la popup
//    d'activation de licence, pas la config du plugin.
// ==========================================================================
if (!$has_valid_license) {
   global $CFG_GLPI;
   $plugins_url = $CFG_GLPI['root_doc'] . '/front/plugin.php';

   // Clé éventuellement déjà saisie ou stockée
   $current_key = $license['license_key'] ?? ($config['license_key'] ?? '');
   $current_key = trim((string)$current_key);

   // Si une date d'expiration existe et est dépassée, on vide la clé pour forcer une nouvelle saisie
   $expires_at = $license['expires_at'] ?? ($config['license_expires_at'] ?? '');
   $expires_at = trim((string)$expires_at);

   if ($expires_at !== '') {
      $ts = strtotime($expires_at . ' 23:59:59');
      if ($ts !== false && $ts < time()) {
         $current_key = '';
      }
   }

   echo "<form method='post' action='config.form.php'>";

   echo "<div class='modal modal-blur fade show' id='glpiaichat-license-activation' tabindex='-1' style='display:block;' aria-modal='true' role='dialog'>";
   echo "  <div class='modal-dialog modal-md modal-dialog-centered' role='document'>";
   echo "    <div class='modal-content'>";
   echo "      <div class='modal-body py-4'>";

   echo "        <h3 class='mb-2'>Activation du plugin Chatbot IA</h3>";
   echo "        <p class='text-muted'>Ce plugin nécessite une clé de licence valide pour être utilisé. Veuillez saisir la clé fournie lors de l'achat.</p>";

   // Champ clé de licence
   echo "        <div class='mb-3'>";
   echo "          <label class='form-label'>Clé de licence</label>";
   echo                Html::input('license_key', [
                           'value' => $current_key,
                           'size'  => 50,
                           'class' => 'form-control',
                        ]);
   echo "          <div class='form-text'>";
   echo "            Saisissez la clé de licence reçue lors de l’achat ou du renouvellement de votre abonnement.<br>";
   echo "            Sans clé valide, le plugin reste inactif.<br>";
   echo "            En cas de perte, contactez le support éditeur.";
   echo "          </div>";
   echo "        </div>";

   // Message d'erreur ou message de licence
   if (!empty($activation_error)) {
      echo "<div class='alert alert-danger' role='alert'>";
      echo Html::entities_deep($activation_error);
      echo "</div>";
   } elseif (!empty($license['message'])) {
      echo "<div class='alert alert-info' role='alert'>";
      echo Html::entities_deep($license['message']);
      echo "</div>";
   }

   echo "      </div>"; // modal-body

   echo "      <div class='modal-footer'>";
   // Bouton Annuler -> retour page plugins
   echo "        <button type='button' class='btn btn-link' onclick=\"window.location.href='{$plugins_url}';\">";
   echo "          Annuler";
   echo "        </button>";

   // Bouton Enregistrer / Activer la licence
   echo          Html::submit('Activer la licence', [
                     'name'  => 'activate_license',
                     'class' => 'btn btn-primary ms-auto'
                  ]);

   echo "      </div>"; // modal-footer
   echo "    </div>";   // modal-content
   echo "  </div>";     // modal-dialog
   echo "</div>";       // modal

   echo "<div class='modal-backdrop fade show'></div>";

   Html::closeForm();
   Html::footer();
   return;
}

// ==========================================================================
// 2) Si la licence EST valide : on affiche la config normale du plugin
//    + informations de licence dans la page.
// ==========================================================================

// Préparation d'un petit texte "Licence valable jusqu'au ..."
$license_help = '';
if (!empty($license['expires_at'])) {
   $ts = strtotime($license['expires_at']);
   if ($ts !== false) {
      $license_help = "Licence valable jusqu’au " . date('d/m/Y', $ts) . ".";
   }
}
if (empty($license_help) && !empty($license['message'])) {
   $license_help = $license['message'];
}

// Préparation d'une version MASQUÉE de la clé (pour affichage seulement)
$license_key_display = '';
if (!empty($license['license_key'])) {
   $raw = (string)$license['license_key'];

   if ($raw === 'BREST EN VUE') {
      // Clé spéciale : on l'affiche telle quelle
      $license_key_display = 'BREST EN VUE';
   } else {
      $len = mb_strlen($raw, 'UTF-8');

      if ($len <= 4) {
         // Très courte : on masque tout
         $license_key_display = str_repeat('•', $len);
      } else {
         // On garde les 4 premiers caractères, on masque le milieu, on garde éventuellement les 4 derniers
         $start = mb_substr($raw, 0, 4, 'UTF-8');
         $end   = '';
         if ($len > 8) {
            $end = mb_substr($raw, -4, null, 'UTF-8');
         }
         $middle_len = max(4, $len - mb_strlen($start, 'UTF-8') - mb_strlen($end, 'UTF-8'));
         $license_key_display = $start . str_repeat('•', $middle_len) . $end;
      }
   }
}

echo "<form method='post' action='config.form.php'>";

echo "<div class='card'>";
echo "  <div class='card-header d-flex justify-content-between align-items-center'>";
echo "    <div>";
echo "      <h3 class='card-title mb-0'>Configuration du chatbot IA</h3>";
echo "      <div class='text-muted small'>Paramétrage de la connexion à l’IA Claude Sonnet et du comportement de l’assistant niveau 1.</div>";
echo "    </div>";
echo "  </div>";

echo "  <div class='card-body'>";

// --------- Bloc : Licence ---------
echo "    <h4 class='subheader mb-3'>Licence</h4>";

echo "    <div class='mb-3 row'>";
echo "      <label class='col-sm-3 col-form-label'>Clé de licence</label>";
echo "      <div class='col-sm-9'>";
echo           Html::input('license_key', [
                     'value'    => $license_key_display,
                     'size'     => 60,
                     'class'    => 'form-control bg-light',
                     'readonly' => 'readonly',
                     'style'    => 'cursor: not-allowed;',
                  ]);
if (!empty($license_help)) {
   echo "        <div class='form-text'>" . Html::entities_deep($license_help) . "</div>";
} else {
   echo "        <div class='form-text'>Licence active.</div>";
}
echo "      </div>";
echo "    </div>";

// --------- Bloc : Connexion à l'IA ---------
echo "    <h4 class='subheader mb-3'>Connexion à l’IA</h4>";

// Numéro de téléphone du support
echo "    <div class='mb-3 row'>";
echo "      <label class='col-sm-3 col-form-label'>Numéro de téléphone du support</label>";
echo "      <div class='col-sm-9'>";
echo           Html::input('support_phone', [
                     'value' => $config['support_phone'] ?? '',
                     'size'  => 60,
                     'class' => 'form-control'
                  ]);
echo "        <div class='form-text'>Ce numéro sera proposé à l’utilisateur pour les cas urgents (bouton « Appeler le support »).</div>";
echo "      </div>";
echo "    </div>";

// URL API IA
echo "    <div class='mb-3 row'>";
echo "      <label class='col-sm-3 col-form-label'>URL API IA</label>";
echo "      <div class='col-sm-9'>";
echo           Html::input('ai_api_url', [
                     'value' => $config['ai_api_url'] ?? 'https://api.anthropic.com/v1/messages',
                     'size'  => 60,
                     'class' => 'form-control'
                  ]);
echo "        <div class='form-text'>URL de l’API Claude Sonnet (par défaut : https://api.anthropic.com/v1/messages).</div>";
echo "      </div>";
echo "    </div>";

// Clé API IA (masquée)
echo "    <div class='mb-4 row'>";
echo "      <label class='col-sm-3 col-form-label'>Clé API IA</label>";
echo "      <div class='col-sm-9'>";
echo           Html::input('ai_api_key', [
                     'value' => $config['ai_api_key'] ?? '',
                     'type'  => 'password',
                     'size'  => 60,
                     'class' => 'form-control'
                  ]);
echo "        <div class='form-text'>Clé API Claude Sonnet (par ex. sk-ant-...).</div>";
echo "      </div>";
echo "    </div>";

// --------- Bloc : Comportement de l’assistant ---------
echo "    <h4 class='subheader mb-3'>Comportement de l’assistant</h4>";

// Prompt système
echo "    <div class='mb-3 row'>";
echo "      <label class='col-sm-3 col-form-label'>Prompt système</label>";
echo "      <div class='col-sm-9'>";
Html::textarea([
   'name'        => 'system_prompt',
   'value'       => $config['system_prompt'] ?? '',
   'rows'        => 10,
   'cols'        => 60,
   'class'       => 'form-control font-monospace',
   'placeholder' => "Contexte métier, règles de niveau 1, exemples de cas à traiter ou à escalader…"
]);
// Html::textarea affiche déjà le champ
echo "        <div class='form-text'>Permet d’ajuster les règles métier (procédures internes, cas particuliers, vocabulaire, etc.).</div>";
echo "      </div>";
echo "    </div>";

echo "  </div>"; // card-body

// Pied de carte avec bouton
echo "  <div class='card-footer text-center'>";
echo        Html::submit(_sx('button', 'Save'), [
               'name'  => 'update',
               'class' => 'btn btn-primary'
            ]);
echo "  </div>";

echo "</div>"; // card

Html::closeForm();

// --------- Popup / Modal après sauvegarde ---------
if ($saved) {
   global $CFG_GLPI;
   $plugins_url = $CFG_GLPI['root_doc'] . '/front/plugin.php';

   echo <<<HTML
<div class="modal modal-blur fade show" id="glpiaichat-config-saved" tabindex="-1" style="display:block;" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body text-center py-4">
        <h3 class="mb-2">Configuration sauvegardée</h3>
        <p class="text-muted mb-0">Les paramètres du chatbot IA ont été mis à jour.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary w-100" onclick="window.location.href='{$plugins_url}';">
          OK
        </button>
      </div>
    </div>
  </div>
</div>
<div class="modal-backdrop fade show"></div>
HTML;
}

Html::footer();
