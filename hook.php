<?php

/**
 * Plugin GLPI AI CHAT - File: hook.php
 * Handles plugin installation and uninstallation hooks.
 */

/**
 * Plugin installation process
 * * @return boolean
 */
function plugin_glpiaichat_install() {
   // No direct SQL queries here to comply with GLPI standards
   // and avoid "Executing direct queries is not allowed!" errors.
   return true;
}

/**
 * Plugin uninstallation process
 * * @return boolean
 */
function plugin_glpiaichat_uninstall() {
   // Optional: clear registered configuration values if needed
   // Config::deleteConfigurationValues('glpiaichat', ['support_phone', 'ai_api_url', 'ai_api_key', 'system_prompt']);
   return true;
}
