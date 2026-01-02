<?php

/**
 * Plugin GLPI AI CHAT - File: public/ajax.chat.php
 * AJAX gateway for the chatbot: handles configuration retrieval, user info, 
 * chat history management, and ticket creation.
 */

include('../../../inc/includes.php');

// Ensure user is logged in
Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

$action  = $_GET['action']  ?? '';
$message = $_GET['message'] ?? '';

$chat = new PluginGlpiaichatChat();

// Check license status for feature restriction
$has_valid_license = PluginGlpiaichatLicense::isValid();

// ---------------------------------------------------------------------
// Reset conversation history in session
// ---------------------------------------------------------------------
if ($action === 'reset_history') {
   unset($_SESSION['plugin_glpiaichat_history']);
   unset($_SESSION['plugin_glpiaichat_free_uses']); // Reset free usage counter
   echo json_encode(['success' => true]);
   exit;
}

// ---------------------------------------------------------------------
// Return display name and initials of the logged-in user
// ---------------------------------------------------------------------
if ($action === 'get_user') {
   $user = new User();
   $name = __('Vous', 'glpiaichat');
   $initials = 'VO';

   if ($user->getFromDB(Session::getLoginUserID())) {
      // GLPI fields: realname = Last Name, firstname = First Name
      $firstname = trim($user->fields['firstname'] ?? '');
      $lastname  = trim($user->fields['realname']  ?? '');

      if ($firstname !== '' || $lastname !== '') {
         // Display format: Firstname LASTNAME
         $full = trim($firstname . ' ' . mb_strtoupper($lastname, 'UTF-8'));
         if ($full !== '') {
            $name = $full;
         }

         // Generate initials
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
// Return UI configuration (icon, color, mode)
// ---------------------------------------------------------------------
if ($action === 'get_config') {
   $config = Config::getConfigurationValues('glpiaichat', [
      'bot_icon_type',
      'bot_icon_text',
      'bot_icon_image_url',
      'bot_color',
      'bot_color_use_theme',
   ]);

   if ($has_valid_license) {
      $icon_type = $config['bot_icon_type']      ?? 'emoji';
      $icon_text = trim((string)($config['bot_icon_text'] ?? ''));
      $icon_img  = trim((string)($config['bot_icon_image_url'] ?? ''));
      $color     = trim((string)($config['bot_color'] ?? ''));

      // Sanitize icon type
      if ($icon_type !== 'image' && $icon_type !== 'emoji') {
         $icon_type = 'emoji';
      }
      if ($icon_text === '') {
         $icon_text = '?';
      }

      $use_theme = !empty($config['bot_color_use_theme']);

      echo json_encode([
         'mode'                => 'full',
         'bot_icon_type'       => $icon_type,
         'bot_icon_text'       => $icon_text,
         'bot_icon_image_url'  => $icon_img,
         'bot_color'           => $color,
         'bot_color_use_theme' => $use_theme,
      ]);
   } else {
      // Degraded mode: enforce default behavior (bubble "?", GLPI theme color)
      echo json_encode([
         'mode'                => 'degraded',
         'bot_icon_type'       => 'emoji',
         'bot_icon_text'       => '?',
         'bot_icon_image_url'  => '',
         'bot_color'           => '',
         'bot_color_use_theme' => true,
      ]);
   }
   exit;
}

// ---------------------------------------------------------------------
// Ticket creation from chatbot
// ---------------------------------------------------------------------
if ($action === 'create_ticket') {
   if (!$has_valid_license) {
      echo json_encode([
         'success' => false,
         'error'   => 'license',
         'message' => __("Le plugin Chatbot IA n'est pas activé (licence manquante ou invalide). La création de tickets via le chatbot est réservée à la version licenciée.", 'glpiaichat'),
      ]);
      exit;
   }

   $question = $_GET['question'] ?? '';
   $answer   = $_GET['answer']   ?? '';
   $title    = $_GET['title']    ?? null;

   $res = $chat->createTicketFromChat($question, $answer, $title);
   echo json_encode($res);
   exit;
}

// ---------------------------------------------------------------------
// Standard Chat Message handling
// ---------------------------------------------------------------------

// Degraded mode: limit to 5 messages per user session
if (!$has_valid_license) {
   $uses = $_SESSION['plugin_glpiaichat_free_uses'] ?? 0;

   if ($uses >= 5) {
      echo json_encode([
         'error'         => __("Vous avez atteint la limite de 5 messages en mode dégradé. Veuillez activer une licence pour continuer à utiliser le chatbot.", 'glpiaichat'),
         'needs_ticket'  => false,
         'suggest_call'  => false,
         'support_phone' => null,
         'ticket_title'  => null,
      ]);
      exit;
   }

   $uses++;
   $_SESSION['plugin_glpiaichat_free_uses'] = $uses;
}

$response = $chat->handleMessage($message);
echo json_encode($response);
