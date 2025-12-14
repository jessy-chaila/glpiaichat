<?php

function plugin_glpiaichat_install() {
   // Pas de SQL direct ici => pas d’erreur "Executing direct queries is not allowed!"
   return true;
}

function plugin_glpiaichat_uninstall() {
   // Tu peux éventuellement nettoyer la config ici si tu veux
   // Config::deleteConfigurationValues('glpiaichat', ['support_phone', 'ai_api_url', 'ai_api_key', 'system_prompt']);
   return true;
}
