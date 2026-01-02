<?php

/**
 * Plugin GLPI AI CHAT - File: setup.php
 * Plugin entry point: handles initialization, versioning, and prerequisites.
 */

/**
 * Plugin initialization function
 * @return void
 */
function plugin_init_glpiaichat() {
   global $PLUGIN_HOOKS;

   // Declare CSRF compatibility
   $PLUGIN_HOOKS['csrf_compliant']['glpiaichat'] = true;

   // Plugin configuration page (visible in the plugin list)
   $PLUGIN_HOOKS['config_page']['glpiaichat'] = 'front/config.form.php';

   // JS / CSS assets injection
   $PLUGIN_HOOKS['add_javascript']['glpiaichat'][] = 'js/chatbot.js';
   $PLUGIN_HOOKS['add_css']['glpiaichat'][]        = 'css/chatbot.css';
}

/**
 * Returns plugin version and metadata
 * @return array
 */
function plugin_version_glpiaichat() {
   return [
      'name'           => __('GLPI AI Chat', 'glpiaichat'),
      'version'        => '1.0.3',
      'author'         => 'COREFORGE, Jessy Chaila',
      'license'        => 'GPLv2+',
      'minGlpiVersion' => '11.0.0',
   ];
}

/**
 * Checks prerequisites before installation
 * @return boolean
 */
function plugin_glpiaichat_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '11.0.0', '<')) {
      echo __('Ce plugin nécessite GLPI >= 11.0.0', 'glpiaichat');
      return false;
   }
   return true;
}

/**
 * Configuration check
 * @return boolean
 */
function plugin_glpiaichat_check_config() {
   return true;
}
