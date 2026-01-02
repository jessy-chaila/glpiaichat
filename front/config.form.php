<?php

/**
 * Plugin GLPI AI CHAT - File: config.form.php
 * Plugin configuration form handling: License, AI Providers, and UI Customization.
 */

include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

$saved = false;
$activation_error = '';
$show_license_modal = false;
$form_error = '';

// Default API URLs for different providers
$anthropic_default_url = 'https://api.anthropic.com/v1/messages';
$openai_default_url    = 'https://api.openai.com/v1/chat/completions';
$xai_default_url       = 'https://api.x.ai/v1/chat/completions';
$google_default_url    = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent';
$mistral_default_url   = 'https://api.mistral.ai/v1/chat/completions';

// AI Providers list
$ai_providers = [
   'anthropic' => 'Claude (Anthropic)',
   'openai'    => 'ChatGPT (OpenAI)',
   'mistral'   => 'Mistral (Mistral AI)',
   'xai'       => 'Grok (xAI)',
   'google'    => 'Gemini (Google)',
   'swiftask'  => 'Swiftask IA',
];

// Currently enabled and configurable providers
$enabled_providers = ['anthropic', 'swiftask', 'openai', 'xai', 'google', 'mistral'];

// ----- License activation handling (from modal) -----
if (isset($_POST['activate_license'])) {
   $result = PluginGlpiaichatLicense::activate($_POST['license_key'] ?? '');
   if (!$result['success']) {
      $activation_error = $result['message'] ?? __('Erreur lors de l’activation de la licence.', 'glpiaichat');
      $show_license_modal = true;
   }
}

// Load current license status
$license = PluginGlpiaichatLicense::getStatus();
$has_valid_license = $license['valid'];

// ----- Plugin configuration saving -----
if (isset($_POST['update'])) {
   $errors = [];

   $posted_model = trim((string)($_POST['ai_model'] ?? ''));
   $posted_key   = trim((string)($_POST['ai_api_key'] ?? ''));

   if ($posted_model === '') {
      $errors[] = __("Le champ « Modèle IA (ID) » est obligatoire.", 'glpiaichat');
   }
   if ($posted_key === '') {
      $errors[] = __("Le champ « Clé API IA » est obligatoire.", 'glpiaichat');
   }

   if ($has_valid_license) {
      $posted_url = trim((string)($_POST['ai_api_url'] ?? ''));
      if ($posted_url === '') {
         $errors[] = __("Le champ « URL API IA » est obligatoire.", 'glpiaichat');
      }
   }

   if (!empty($errors)) {
      $form_error = implode('<br>', array_map('Html::entities_deep', $errors));
   } else {
      if ($has_valid_license) {

         $selected_provider = $_POST['ai_provider'] ?? 'anthropic';
         if (!array_key_exists($selected_provider, $ai_providers)) {
            $selected_provider = 'anthropic';
         }

         // Backend normalization (xAI uses OpenAI provider logic)
         $technical_provider = ($selected_provider === 'xai') ? 'openai' : $selected_provider;

         if ($selected_provider === 'anthropic' && empty(trim($_POST['ai_api_url'] ?? ''))) {
            $_POST['ai_api_url'] = $anthropic_default_url;
         }

         global $CFG_GLPI;
         $existing_icon_url = $_POST['bot_icon_image_url_current'] ?? '';
         $new_icon_url      = $existing_icon_url;

         // Handle chatbot icon upload
         if (isset($_FILES['bot_icon_image_file']) && $_FILES['bot_icon_image_file']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['bot_icon_image_file']['tmp_name'];
            $name = $_FILES['bot_icon_image_file']['name'];
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])) {
               $dest_dir = GLPI_ROOT . '/plugins/glpiaichat/public/img';
               if (!is_dir($dest_dir)) {
                  @mkdir($dest_dir, 0755, true);
               }
               $dest_name = uniqid('bot_icon_', true) . '.' . $ext;
               $dest_path = $dest_dir . '/' . $dest_name;
               if (@move_uploaded_file($tmp, $dest_path)) {
                  $new_icon_url = $CFG_GLPI['root_doc'] . '/plugins/glpiaichat/public/img/' . $dest_name;
               }
            }
         }

         Config::setConfigurationValues('glpiaichat', [
            'support_phone'       => $_POST['support_phone'] ?? '',
            'ai_api_url'          => $_POST['ai_api_url'] ?? '',
            'ai_api_key'          => $_POST['ai_api_key'] ?? '',
            'system_prompt'       => $_POST['system_prompt'] ?? '',
            'ai_provider'         => $technical_provider,
            'ai_provider_ui'      => $selected_provider,
            'ai_model'            => $_POST['ai_model'] ?? '',
            'bot_icon_type'       => $_POST['bot_icon_type'] ?? 'emoji',
            'bot_icon_text'       => $_POST['bot_icon_text'] ?? '',
            'bot_icon_image_url'  => $new_icon_url,
            'bot_color'           => $_POST['bot_color'] ?? '',
            'bot_color_use_theme' => !empty($_POST['bot_color_use_theme']) ? 1 : 0,
         ]);

      } else {
         // Fallback configuration for unlicensed mode
         Config::setConfigurationValues('glpiaichat', [
            'ai_api_key'  => $_POST['ai_api_key'] ?? '',
            'ai_provider' => 'anthropic',
            'ai_model'    => $_POST['ai_model'] ?? '',
            'ai_api_url'  => $anthropic_default_url,
         ]);
      }
      $saved = true;
   }
}

// Toggle license modal
if (isset($_POST['show_license_modal']) && !$has_valid_license) {
   $show_license_modal = true;
}

// Load current configuration
$config = Config::getConfigurationValues('glpiaichat', [
   'support_phone',
   'ai_api_url',
   'ai_api_key',
   'system_prompt',
   'ai_provider',
   'ai_provider_ui',
   'ai_model',
   'bot_icon_type',
   'bot_icon_text',
   'bot_icon_image_url',
   'bot_color',
   'bot_color_use_theme',
   'license_key',
   'license_status',
   'license_expires_at',
   'license_message',
]);

$current_provider = $config['ai_provider_ui']
   ?? $config['ai_provider']
   ?? 'anthropic';
if (!array_key_exists($current_provider, $ai_providers)) {
   $current_provider = 'anthropic';
}

$current_model = trim((string)($config['ai_model'] ?? ''));
$current_icon_type           = $config['bot_icon_type'] ?? 'emoji';
$current_icon_text           = $config['bot_icon_text'] ?? '';
$current_icon_image_url      = $config['bot_icon_image_url'] ?? '';
$current_bot_color           = $config['bot_color'] ?? '';
$current_bot_color_use_theme = !empty($config['bot_color_use_theme']);

Html::header(
   __('Configuration du chatbot IA', 'glpiaichat'),
   $_SERVER['PHP_SELF'],
   'config',
   'plugins'
);

// Prepare license info text
$license_help = '';
if ($has_valid_license && !empty($license['expires_at'])) {
   $ts = strtotime($license['expires_at']);
   if ($ts !== false) {
      $license_help = __("Licence valable jusqu’au ", 'glpiaichat') . date('d/m/Y', $ts) . ".";
   }
}
if ($has_valid_license && empty($license_help) && !empty($license['message'])) {
   $license_help = $license['message'];
}

// Obfuscated license key display
$license_key_display = '';
if ($has_valid_license && !empty($license['license_key'])) {
   $raw = (string)$license['license_key'];
   $len = mb_strlen($raw, 'UTF-8');
   if ($len <= 4) {
      $license_key_display = str_repeat('•', $len);
   } else {
      $start = mb_substr($raw, 0, 4, 'UTF-8');
      $end   = ($len > 8) ? mb_substr($raw, -4, null, 'UTF-8') : '';
      $middle_len = max(4, $len - mb_strlen($start, 'UTF-8') - mb_strlen($end, 'UTF-8'));
      $license_key_display = $start . str_repeat('•', $middle_len) . $end;
   }
}

// ==========================================================================
// MAIN FORM
// ==========================================================================
echo "<form method='post' action='config.form.php' enctype='multipart/form-data'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card'>";
echo " <div class='card-header d-flex justify-content-between align-items-center'>";
echo "  <div>";
echo "   <h3 class='card-title mb-0'>" . __('Configuration du chatbot IA', 'glpiaichat') . "</h3>";
echo ($has_valid_license)
   ? "   <div class='text-muted small'>" . __('Plugin activé (mode complet). Paramétrage de la connexion à l’IA et du comportement de l’assistant.', 'glpiaichat') . "</div>"
   : "   <div class='text-muted small'>" . __('Plugin en mode dégradé (sans licence) : configuration limitée, 5 messages gratuits max.', 'glpiaichat') . "</div>";
echo "  </div>";
echo " </div>";
echo " <div class='card-body'>";

if ($form_error !== '') {
   echo "<div class='alert alert-danger mb-4'>" . $form_error . "</div>";
}

// --------- Block: License ---------
echo " <h4 class='subheader mb-3'>" . __('Licence', 'glpiaichat') . "</h4>";
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('État de la licence', 'glpiaichat') . "</label>";
echo "  <div class='col-sm-9'>";
if ($has_valid_license) {
   echo "   <div class='mb-2'>";
   echo Html::input('license_key', [
      'value' => $license_key_display,
      'size'  => 60,
      'class' => 'form-control bg-light',
      'readonly' => 'readonly',
      'style' => 'cursor: not-allowed;',
   ]);
   echo "   </div>";
   echo !empty($license_help)
      ? "   <div class='form-text'>" . Html::entities_deep($license_help) . "</div>"
      : "   <div class='form-text'>" . __('Licence active.', 'glpiaichat') . "</div>";
} else {
   echo "   <div class='d-flex flex-column gap-2'>";
   echo "    <div class='alert alert-warning mb-0' role='alert' style='max-width: 100%;'>";
   echo "     <strong>" . __('Mode dégradé :', 'glpiaichat') . "</strong> " . __('licence non activée.', 'glpiaichat') . "<br>";
   echo "     <ul class='mb-0 ps-4'>";
   echo "      <li>" . __('Chat limité à 5 messages par session utilisateur.', 'glpiaichat') . "</li>";
   echo "      <li>" . __('Création de tickets et options avancées réservées à la version licenciée.', 'glpiaichat') . "</li>";
   echo "     </ul>";
   echo "    </div>";
   echo Html::submit(__('Activer la licence', 'glpiaichat'), [
      'name'  => 'show_license_modal',
      'class' => 'btn btn-outline-danger align-self-start'
   ]);
   echo "   </div>";
}
echo "  </div>";
echo " </div>";

// --------- Block: AI Connection ---------
echo " <h4 class='subheader mb-3'>" . __('Connexion à l’IA', 'glpiaichat') . "</h4>";

// AI Provider
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Fournisseur IA', 'glpiaichat') . " <span class='red'>*</span></label>";
echo "  <div class='col-sm-9'>";
echo "   <div style='max-width:60ch;'>";
echo "    <select name='ai_provider' class='form-select' " . (!$has_valid_license ? "disabled" : "") . ">";
foreach ($ai_providers as $code => $label) {
   $selected      = ($code === $current_provider) ? " selected" : "";
   $is_enabled    = in_array($code, $enabled_providers, true);
   $disabled_opt  = $is_enabled ? "" : " disabled";
   $display_label = $is_enabled ? $label : $label . ' – ' . __('bientôt disponible', 'glpiaichat');
   echo "<option value='" . Html::entities_deep($code) . "'$selected$disabled_opt>" . Html::entities_deep($display_label) . "</option>";
}
echo "    </select>";
echo "   </div>";
if (!$has_valid_license) {
   echo "   <div class='form-text'>" . __('En mode dégradé, le fournisseur est imposé sur Claude (Anthropic).', 'glpiaichat') . "</div>";
} else {
   echo "   <div class='form-text'>" . __('Sélectionnez un fournisseur parmi ceux disponible dans la liste.', 'glpiaichat') . "</div>";
}
echo "  </div>";
echo " </div>";

// AI Model
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Modèle IA (ID)', 'glpiaichat') . " <span class='red'>*</span></label>";
echo "  <div class='col-sm-9'>";
$ai_model_input = [
   'value' => $current_model,
   'size'  => 60,
   'class' => 'form-control',
];
echo Html::input('ai_model', $ai_model_input);

echo "   <div id='ai_model_help' class='form-text'>";
if ($current_provider === 'openai') {
   echo __('Modèles recommandés OpenAI : <code>gpt-4o</code>, <code>gpt-4o-mini</code>. Les modèles compatibles avec l’API OpenAI (Groq, etc.) sont également utilisables.', 'glpiaichat');
} elseif ($current_provider === 'xai') {
   echo __('Modèles Grok (xAI) disponibles : <code>grok-2-1212</code>, <code>grok-beta</code> (ou le dernier en date). Les modèles compatibles avec l’API OpenAI (OpenAI, Groq, etc.) sont également utilisables.', 'glpiaichat');
} elseif ($current_provider === 'anthropic') {
   echo __('Exemples : <code>claude-3-5-sonnet-20241022</code>, <code>claude-3-opus-20240229</code>, <code>claude-3-haiku-20240307</code>.', 'glpiaichat');
} elseif ($current_provider === 'google') {
   echo __('Exemples de modèles Gemini : <code>gemini-2.0-flash</code>, <code>gemini-1.5-flash</code>, <code>gemini-1.5-pro</code>.', 'glpiaichat');
} elseif ($current_provider === 'mistral') {
   echo __('Exemples de modèles Mistral : <code>mistral-small-latest</code>, <code>mistral-large-latest</code>, <code>open-mistral-7b</code>.', 'glpiaichat');
} else {
   echo __('Renseignez l’ID exact du modèle selon la documentation de votre fournisseur.', 'glpiaichat');
}
echo "   </div>";
echo "  </div>";
echo " </div>";

// AI API URL
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('URL API IA', 'glpiaichat') . " <span class='red'>*</span></label>";
echo "  <div class='col-sm-9'>";

$default_url = $anthropic_default_url;
if ($current_provider === 'openai' || $current_provider === 'xai') {
    $default_url = ($current_provider === 'openai') ? $openai_default_url : $xai_default_url;
} elseif ($current_provider === 'mistral') {
    $default_url = $mistral_default_url;
}

$ai_api_url_input = [
   'value' => $config['ai_api_url'] ?? $default_url,
   'size'  => 60,
   'class' => 'form-control'
];
if (!$has_valid_license) {
   $ai_api_url_input['readonly'] = 'readonly';
   $ai_api_url_input['class']   .= ' bg-light';
   $ai_api_url_input['value']    = $anthropic_default_url;
}
echo Html::input('ai_api_url', $ai_api_url_input);

if (!$has_valid_license) {
   echo "   <div class='form-text'>" . __('En mode dégradé, l’utilisation est limitée à l’API Anthropic (Claude).', 'glpiaichat') . "</div>";
} else {
   echo "   <div id='ai_api_url_help' class='form-text'>";
   if ($current_provider === 'openai' || $current_provider === 'xai') {
      $provider_name   = ($current_provider === 'openai') ? 'OpenAI' : 'Grok (xAI)';
      $default_display = ($current_provider === 'openai') ? $openai_default_url : $xai_default_url;
      echo sprintf(__('URL par défaut pour %s : <code>%s</code>.', 'glpiaichat'), $provider_name, $default_display);
   } elseif ($current_provider === 'mistral') {
      echo sprintf(__('URL par défaut pour Mistral (Mistral AI) : <code>%s</code>.', 'glpiaichat'), $mistral_default_url);
   } elseif ($current_provider === 'google') {
      echo sprintf(__('URL par défaut pour Gemini (Google) : <code>%s</code><br>Adaptez-la selon le modèle et la version de l’API utilisés, en suivant la documentation Gemini.', 'glpiaichat'), $google_default_url);
   } else {
      echo sprintf(__('URL de l’API du fournisseur IA (par défaut : <code>%s</code> pour Claude).', 'glpiaichat'), $anthropic_default_url);
   }
   echo "   </div>";
}
echo "  </div>";
echo " </div>";

// AI API Key
echo " <div class='mb-4 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Clé API IA', 'glpiaichat') . " <span class='red'>*</span></label>";
echo "  <div class='col-sm-9'>";
$ai_api_key_input = [
   'value' => $config['ai_api_key'] ?? '',
   'type'  => 'password',
   'size'  => 60,
   'class' => 'form-control'
];
echo Html::input('ai_api_key', $ai_api_key_input);
echo ($has_valid_license)
   ? "   <div class='form-text'>" . __('Clé API du fournisseur sélectionné (OpenAI, Groq, Claude, Swiftask, Mistral, etc.).', 'glpiaichat') . "</div>"
   : "   <div class='form-text'>" . __('Saisissez une clé API valide pour Claude afin de tester le chatbot en mode dégradé (5 messages gratuits).', 'glpiaichat') . "</div>";
echo "  </div>";
echo " </div>";

// --------- Block: Assistant Behavior ---------
echo " <h4 class='subheader mb-3'>" . __('Comportement de l’assistant', 'glpiaichat') . "</h4>";

// Support Phone
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Numéro de téléphone du support', 'glpiaichat') . "</label>";
echo "  <div class='col-sm-9'>";
$support_phone_input = [
   'value' => $config['support_phone'] ?? '',
   'size'  => 60,
   'class' => 'form-control'
];
if (!$has_valid_license) {
   $support_phone_input['readonly'] = 'readonly';
   $support_phone_input['class']   .= ' bg-light';
}
echo Html::input('support_phone', $support_phone_input);
echo ($has_valid_license)
   ? "   <div class='form-text'>" . __('Ce numéro sera proposé à l’utilisateur pour les cas urgents (bouton « Appeler le support »).', 'glpiaichat') . "</div>"
   : "   <div class='form-text'>" . __('Champ réservé à la version licenciée. En mode dégradé, ce numéro n’est pas utilisé.', 'glpiaichat') . "</div>";
echo "  </div>";
echo " </div>";

// System Prompt
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Prompt système', 'glpiaichat') . "</label>";
echo "  <div class='col-sm-9'>";
if ($has_valid_license && $current_provider !== 'swiftask') {
   Html::textarea([
      'name'  => 'system_prompt',
      'value' => $config['system_prompt'] ?? '',
      'rows'  => 10,
      'cols'  => 60,
      'class' => 'form-control font-monospace',
      'placeholder' => __("Contexte métier, règles de niveau 1, exemples de cas à traiter ou à escalader…", 'glpiaichat')
   ]);
} else {
   $val = Html::entities_deep($config['system_prompt'] ?? '');
   echo "<textarea name='system_prompt' rows='10' cols='60' class='form-control font-monospace bg-light' readonly='readonly' style='cursor: not-allowed;'>{$val}</textarea>";
}
if (!$has_valid_license) {
   echo "   <div class='form-text'>" . __('Modification du prompt système réservée à la version licenciée.', 'glpiaichat') . "</div>";
} elseif ($current_provider === 'swiftask') {
   echo "   <div class='form-text'>" . __('Pour modifier le comportement de Swiftask, utilisez les instructions de l’agent directement dans Swiftask. Le prompt système GLPI n’est pas utilisé pour ce fournisseur.', 'glpiaichat') . "</div>";
} else {
   echo "   <div class='form-text'>" . __('Permet d’ajuster les règles métier (procédures internes, cas particuliers, vocabulaire, etc.).', 'glpiaichat') . "</div>";
}
echo "  </div>";
echo " </div>";

// --------- Block: Interface Customization ---------
echo " <h4 class='subheader mb-3 mt-4'>" . __('Personnalisation de l’interface', 'glpiaichat') . "</h4>";

// Bot Icon Type
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Type d’icône du bot', 'glpiaichat') . "</label>";
echo "  <div class='col-sm-9'>";
$icon_type_select_disabled = $has_valid_license ? "" : " disabled";
echo "   <div style='max-width:30ch;'>";
echo "    <select name='bot_icon_type' id='bot_icon_type' class='form-select'$icon_type_select_disabled>";
$icon_types = [
   'emoji' => __('Texte / emoji', 'glpiaichat'),
   'image' => __('Image (upload)', 'glpiaichat'),
];
foreach ($icon_types as $code => $label) {
   $selected = ($code === $current_icon_type) ? " selected" : "";
   echo "<option value='" . Html::entities_deep($code) . "'$selected>" . Html::entities_deep($label) . "</option>";
}
echo "    </select>";
echo "   </div>";
if ($has_valid_license) {
   echo "   <div class='form-text'>" . __('Choisissez si l’icône de la bulle du bot est un texte/emoji ou une image importée.', 'glpiaichat') . "</div>";
} else {
   echo "   <div class='form-text'>" . __('Personnalisation de l’icône réservée à la version licenciée. En mode dégradé, l’icône par défaut est utilisée.', 'glpiaichat') . "</div>";
}
echo "  </div>";
echo " </div>";

// Icon Text / Emoji
echo " <div class='mb-3 row' id='row_bot_icon_text'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Texte / emoji de l’icône', 'glpiaichat') . "</label>";
echo "  <div class='col-sm-9'>";
$icon_text_input = [
   'value' => $current_icon_text,
   'size'  => 20,
   'class' => 'form-control',
];
if (!$has_valid_license) {
   $icon_text_input['readonly'] = 'readonly';
   $icon_text_input['class']   .= ' bg-light';
}
echo Html::input('bot_icon_text', $icon_text_input);
if ($has_valid_license) {
   echo "   <div class='form-text'>" . sprintf(__('Exemples : <code>%s</code>, <code>%s</code>, <code>%s</code>. Utilisé lorsque le type d’icône est « Texte / emoji ».', 'glpiaichat'), '?', 'IA', '🤖') . "</div>";
} else {
   echo "   <div class='form-text'>" . __('Personnalisation de l’icône réservée à la version licenciée.', 'glpiaichat') . "</div>";
}
echo "  </div>";
echo " </div>";

// Icon Image Upload
echo " <div class='mb-3 row' id='row_bot_icon_image'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Image d’icône (upload)', 'glpiaichat') . "</label>";
echo "  <div class='col-sm-9'>";
$image_file_input = [
   'name'  => 'bot_icon_image_file',
   'type'  => 'file',
   'class' => 'form-control',
];
if (!$has_valid_license) {
   $image_file_input['disabled'] = 'disabled';
   $image_file_input['class']   .= ' bg-light';
}
echo Html::input('bot_icon_image_file', $image_file_input);
echo Html::input('bot_icon_image_url_current', [
   'type'  => 'hidden',
   'value' => $current_icon_image_url,
]);
if ($current_icon_image_url !== '') {
   echo "   <div class='mt-2'>";
   echo "    <span class='form-text'>" . __('Icône actuelle :', 'glpiaichat') . "</span><br>";
   echo "    <img src='" . Html::entities_deep($current_icon_image_url) . "' alt='" . __('Icône actuelle', 'glpiaichat') . "' style='max-height:48px; max-width:48px; border-radius:50%; border:1px solid #ddd; background:#fff;' />";
   echo "   </div>";
}
if ($has_valid_license) {
   echo "   <div class='form-text'>" . __('Importez une image (PNG, JPG, GIF, WEBP, SVG) utilisée comme icône lorsque le type est « Image (upload) ».', 'glpiaichat') . "</div>";
} else {
   echo "   <div class='form-text'>" . __('Personnalisation de l’icône réservée à la version licenciée.', 'glpiaichat') . "</div>";
}
echo "  </div>";
echo " </div>";

// Bot Color
echo " <div class='mb-3 row'>";
echo "  <label class='col-sm-3 col-form-label'>" . __('Couleur principale du bot', 'glpiaichat') . "</label>";
echo "  <div class='col-sm-9'>";
if ($has_valid_license) {
   $use_theme_checked = $current_bot_color_use_theme ? " checked" : "";
   echo "   <div class='form-check mb-2'>";
   echo "    <input type='checkbox' class='form-check-input' id='bot_color_use_theme' name='bot_color_use_theme' value='1'{$use_theme_checked}>";
   echo "    <label class='form-check-label' for='bot_color_use_theme'>" . __('Utiliser la couleur du thème GLPI (par défaut)', 'glpiaichat') . "</label>";
   echo "   </div>";
   $bot_color_value = $current_bot_color ?: '#2563eb';
   echo "   <div id='bot_color_picker_row'>";
   echo Html::input('bot_color', [
      'id'    => 'bot_color',
      'type'  => 'color',
      'value' => $bot_color_value,
      'class' => 'form-control form-control-color',
      'style' => 'padding:0; width:4rem; height:2.5rem;',
   ]);
   echo "   </div>";
   echo "   <div class='form-text'>" . __('Décochez l’option ci-dessus pour définir une couleur personnalisée pour la bulle, les messages utilisateur et les boutons.', 'glpiaichat') . "</div>";
} else {
   $bot_color_input = [
      'value'      => '',
      'size'       => 10,
      'class'      => 'form-control bg-light',
      'placeholder'=> '#RRGGBB',
      'readonly'   => 'readonly',
   ];
   echo Html::input('bot_color', $bot_color_input);
   echo "   <div class='form-text'>" . __('Personnalisation de la couleur réservée à la version licenciée. En mode dégradé, la couleur du thème GLPI est utilisée.', 'glpiaichat') . "</div>";
}
echo "  </div>";
echo " </div>";

echo " </div>"; // card-body
echo " <div class='card-footer text-center'>";
echo ($has_valid_license)
   ? Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary'])
   : Html::submit(__('Enregistrer (mode dégradé)', 'glpiaichat'), ['name' => 'update', 'class' => 'btn btn-primary']);
echo " </div>";
echo "</div>"; // card
Html::closeForm();

// ----------------------------------------------------------------------
// License Activation Modal
// ----------------------------------------------------------------------
if ($show_license_modal && !$has_valid_license) {
   global $CFG_GLPI;
   $plugins_url = $CFG_GLPI['root_doc'] . '/front/plugin.php';
   $current_key = trim((string)($license['license_key'] ?? $config['license_key'] ?? ''));
   echo "<div class='modal modal-blur fade show' id='glpiaichat-license-activation' tabindex='-1' style='display:block;' aria-modal='true' role='dialog'>";
   echo " <div class='modal-dialog modal-md modal-dialog-centered' role='document'>";
   echo "  <div class='modal-content'>";
   echo "   <div class='modal-body py-4'>";
   echo "    <h3 class='mb-2'>" . __('Activation du plugin Chatbot IA', 'glpiaichat') . "</h3>";
   echo "    <p class='text-muted'>" . __('Ce plugin nécessite une clé de licence valide pour débloquer toutes les fonctionnalités. Veuillez saisir la clé fournie lors de l\'achat.', 'glpiaichat') . "</p>";
   echo "    <div class='mb-3'>";
   echo "     <label class='form-label'>" . __('Clé de licence', 'glpiaichat') . "</label>";
   echo Html::input('license_key', [
      'value' => $current_key,
      'size'  => 50,
      'class' => 'form-control',
   ]);
   echo "     <div class='form-text'>";
   echo __("Saisissez la clé de licence reçue lors de l’achat ou du renouvellement de votre abonnement.", 'glpiaichat') . "<br>";
   echo __("Sans clé valide, le plugin reste inactif en mode dégradé.", 'glpiaichat') . "<br>";
   echo __("En cas de perte, contactez le support éditeur.", 'glpiaichat');
   echo "     </div>";
   echo "    </div>";
   if (!empty($activation_error)) {
      echo "   <div class='alert alert-danger' role='alert'>";
      echo Html::entities_deep($activation_error);
      echo "   </div>";
   } elseif (!empty($license['message'])) {
      echo "   <div class='alert alert-info' role='alert'>";
      echo Html::entities_deep($license['message']);
      echo "   </div>";
   }
   echo "   </div>";
   echo "   <div class='modal-footer'>";
   echo "    <button type='button' class='btn btn-link' onclick=\"window.location.href='{$plugins_url}';\">" . __('Annuler', 'glpiaichat') . "</button>";
   echo Html::submit(__('Activer la licence', 'glpiaichat'), [
      'name'  => 'activate_license',
      'class' => 'btn btn-primary ms-auto'
   ]);
   echo "   </div>";
   echo "  </div>";
   echo " </div>";
   echo "</div>";
   echo "<div class='modal-backdrop fade show'></div>";
}

// Config saved notification
if ($saved && $form_error === '') {
   global $CFG_GLPI;
   $config_url = $CFG_GLPI['root_doc'] . '/plugins/glpiaichat/front/config.form.php';
   $title_saved = __('Configuration sauvegardée', 'glpiaichat');
   $msg_saved = __('Les paramètres du chatbot IA ont été mis à jour.', 'glpiaichat');

   echo <<<HTML
<div class="modal modal-blur fade show" id="glpiaichat-config-saved" tabindex="-1" style="display:block;" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body text-center py-4">
        <h3 class="mb-2">{$title_saved}</h3>
        <p class="text-muted mb-0">{$msg_saved}</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary w-100" onclick="window.location.href='{$config_url}';">
          OK
        </button>
      </div>
    </div>
  </div>
</div>
<div class="modal-backdrop fade show"></div>
HTML;
}

?>
<script>
/**
 * UI script for dynamic visibility of configuration rows and provider help.
 */
document.addEventListener('DOMContentLoaded', function () {
   var typeSelect = document.getElementById('bot_icon_type');
   var rowText    = document.getElementById('row_bot_icon_text');
   var rowImage   = document.getElementById('row_bot_icon_image');

   function updateIconRows() {
      if (!typeSelect || !rowText || !rowImage) return;
      var v = typeSelect.value;
      rowText.style.display  = (v === 'image') ? 'none' : '';
      rowImage.style.display = (v === 'image') ? '' : 'none';
   }
   if (typeSelect) {
      typeSelect.addEventListener('change', updateIconRows);
   }
   updateIconRows();

   var useThemeCheckbox = document.getElementById('bot_color_use_theme');
   var colorRow         = document.getElementById('bot_color_picker_row');

   function updateColorState() {
      if (!useThemeCheckbox || !colorRow) return;
      colorRow.style.display = useThemeCheckbox.checked ? 'none' : '';
   }
   if (useThemeCheckbox) {
      useThemeCheckbox.addEventListener('change', updateColorState);
      updateColorState();
   }

   var providerSelect = document.querySelector("select[name='ai_provider']");

   function updateProviderHelp() {
      if (!providerSelect) return;
      var provider     = providerSelect.value;
      var modelHelpDiv = document.getElementById('ai_model_help');
      var urlHelpDiv   = document.getElementById('ai_api_url_help');

      var modelHelpByProvider = {
         'openai': "<?php echo __('Modèles recommandés OpenAI : <code>gpt-4o</code>, <code>gpt-4o-mini</code>. Les modèles compatibles avec l’API OpenAI (Groq, etc.) sont également utilisables.', 'glpiaichat'); ?>",
         'xai': "<?php echo __('Modèles Grok (xAI) disponibles : <code>grok-2-1212</code>, <code>grok-beta</code> (ou le dernier en date). Les modèles compatibles avec l’API OpenAI (OpenAI, Groq, etc.) sont également utilisables.', 'glpiaichat'); ?>",
         'mistral': "<?php echo __('Exemples de modèles Mistral : <code>mistral-small-latest</code>, <code>mistral-large-latest</code>, <code>open-mistral-7b</code>.', 'glpiaichat'); ?>",
         'anthropic': "<?php echo __('Exemples : <code>claude-3-5-sonnet-20241022</code>, <code>claude-3-opus-20240229</code>, <code>claude-3-haiku-20240307</code>.', 'glpiaichat'); ?>",
         'google': "<?php echo __('Exemples de modèles Gemini : <code>gemini-2.0-flash</code>, <code>gemini-1.5-flash</code>, <code>gemini-1.5-pro</code>.', 'glpiaichat'); ?>",
         'swiftask': "<?php echo __('Renseignez l’ID du modèle configuré dans Swiftask, ou laissez-le selon la documentation Swiftask.', 'glpiaichat'); ?>",
         'default': "<?php echo __('Renseignez l’ID exact du modèle selon la documentation de votre fournisseur.', 'glpiaichat'); ?>"
      };

      var urlHelpByProvider = {
         'openai': "<?php echo sprintf(__('URL par défaut pour OpenAI : <code>%s</code>.', 'glpiaichat'), 'https://api.openai.com/v1/chat/completions'); ?>",
         'xai': "<?php echo sprintf(__('URL par défaut pour Grok (xAI) : <code>%s</code>.', 'glpiaichat'), 'https://api.x.ai/v1/chat/completions'); ?>",
         'mistral': "<?php echo sprintf(__('URL par défaut pour Mistral (Mistral AI) : <code>%s</code>.', 'glpiaichat'), 'https://api.mistral.ai/v1/chat/completions'); ?>",
         'google': "<?php echo sprintf(__('URL par défaut pour Gemini (Google) : <code>%s</code><br>Par exemple pour <code>gemini-2.0-flash</code> : <code>%s</code>.', 'glpiaichat'), 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent'); ?>",
         'anthropic': "<?php echo sprintf(__('URL de l’API du fournisseur IA (par défaut : <code>%s</code> pour Claude).', 'glpiaichat'), 'https://api.anthropic.com/v1/messages'); ?>",
         'swiftask': "<?php echo __('URL de l’API Swiftask fournie par votre instance.', 'glpiaichat'); ?>",
         'default': "<?php echo __('URL de l’API du fournisseur IA (consultez la documentation de votre fournisseur).', 'glpiaichat'); ?>"
      };

      if (modelHelpDiv) modelHelpDiv.innerHTML = modelHelpByProvider[provider] || modelHelpByProvider['default'];
      if (urlHelpDiv) urlHelpDiv.innerHTML = urlHelpByProvider[provider] || urlHelpByProvider['default'];
   }

   if (providerSelect) {
      providerSelect.addEventListener('change', function () {
         var modelInput = document.querySelector("input[name='ai_model']");
         var urlInput   = document.querySelector("input[name='ai_api_url']");
         var keyInput   = document.querySelector("input[name='ai_api_key']");
         if (modelInput) modelInput.value = '';
         if (urlInput) urlInput.value = '';
         if (keyInput) keyInput.value = '';
         updateProviderHelp();
      });
      updateProviderHelp();
   }
});
</script>
<?php
Html::footer();
