<?php

function plugin_init_glpiaichat() {
   global $PLUGIN_HOOKS;

   // Indique que le plugin est compatible CSRF
   $PLUGIN_HOOKS['csrf_compliant']['glpiaichat'] = true;

   // Page de configuration du plugin
   // => c'est ça qui fait apparaître le bouton "Config" dans la liste des plugins
   $PLUGIN_HOOKS['config_page']['glpiaichat'] = 'front/config.form.php';

   // (Optionnel) ressources JS / CSS
   $PLUGIN_HOOKS['add_javascript']['glpiaichat'][] = 'js/chatbot.js';
   $PLUGIN_HOOKS['add_css']['glpiaichat'][]        = 'css/chatbot.css';
}

function plugin_version_glpiaichat() {
   return [
      'name'           => 'GLPI AI Chat',
      'version'        => '1.0.0',
      'author'         => 'Jessy CHAILA',
      'license'        => 'GPLv2+',
      'minGlpiVersion' => '11.0.0',
   ];
}

function plugin_glpiaichat_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '11.0.0', '<')) {
      echo "Ce plugin nécessite GLPI >= 11.0.0";
      return false;
   }
   return true;
}

function plugin_glpiaichat_check_config() {
   return true;
}
