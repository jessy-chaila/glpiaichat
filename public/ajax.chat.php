<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

$action  = $_GET['action']  ?? '';
$message = $_GET['message'] ?? '';

$chat = new PluginGlpiaichatChat();

// ---------------------------------------------------------------------
// Vérification de la licence :
// - On laisse passer uniquement get_user et reset_history (pour l'UI).
// - Tout le reste est bloqué si la licence n'est pas valide.
// ---------------------------------------------------------------------
if (!PluginGlpiaichatLicense::isValid()
    && !in_array($action, ['get_user', 'reset_history'], true)) {

   // Pour le message d'erreur, on peut éventuellement récupérer le téléphone
   $cfg = Config::getConfigurationValues('glpiaichat', ['support_phone']);

   if ($action === 'create_ticket') {
      // Format adapté à la création de ticket
      echo json_encode([
         'success' => false,
         'error'   => 'license',
         'message' => "Le plugin Chatbot IA n'est pas activé (licence manquante ou invalide). Impossible de créer un ticket via le chatbot.",
      ]);
   } else {
      // Réponse formatée comme une erreur pour le chat
      echo json_encode([
         'error'         => "Le service d’assistance automatique n’est pas disponible car le plugin n’est pas activé (licence manquante ou invalide). Veuillez contacter votre administrateur.",
         'needs_ticket'  => false,
         'suggest_call'  => !empty($cfg['support_phone']),
         'support_phone' => $cfg['support_phone'] ?? null,
         'ticket_title'  => null,
      ]);
   }

   exit;
}

// ---------------------------------------------------------------------
// Réinitialiser l'historique de conversation en session
// ---------------------------------------------------------------------
if ($action === 'reset_history') {
   unset($_SESSION['plugin_glpiaichat_history']);
   echo json_encode(['success' => true]);
   exit;
}

// ---------------------------------------------------------------------
// Renvoyer le nom et les initiales de l'utilisateur connecté
// ---------------------------------------------------------------------
if ($action === 'get_user') {
   $user = new User();
   $name = 'Vous';
   $initials = 'VO';

   if ($user->getFromDB(Session::getLoginUserID())) {
      // GLPI standard : realname = Nom, firstname = Prénom
      $firstname = trim($user->fields['firstname'] ?? '');  // Prénom
      $lastname  = trim($user->fields['realname']  ?? '');  // Nom

      if ($firstname !== '' || $lastname !== '') {
         // Affichage : Prénom NOM (NOM en majuscules)
         $full = trim($firstname . ' ' . mb_strtoupper($lastname, 'UTF-8'));
         if ($full !== '') {
            $name = $full;
         }

         // Initiales basées sur Prénom + Nom
         $initials = '';
         if ($firstname !== '') {
            $initials .= mb_strtoupper(mb_substr($firstname, 0, 1, 'UTF-8'), 'UTF-8');
         }
         if ($lastname !== '') {
            $initials .= mb_strtoupper(mb_substr($lastname, 0, 1, 'UTF-8'), 'UTF-8');
         }
         if ($initials === '') {
            $initials = 'US';
         }
      }
   }

   echo json_encode([
      'name'      => $name,
      'initials'  => $initials,
   ]);
   exit;
}

// ---------------------------------------------------------------------
// Création de ticket depuis le chatbot
// ---------------------------------------------------------------------
if ($action === 'create_ticket') {
   $question = $_GET['question'] ?? '';
   $answer   = $_GET['answer']   ?? '';
   $title    = $_GET['title']    ?? null;

   $res = $chat->createTicketFromChat($question, $answer, $title);
   echo json_encode($res);
   exit;
}

// ---------------------------------------------------------------------
// Message normal (chat)
// ---------------------------------------------------------------------
$response = $chat->handleMessage($message);
echo json_encode($response);
