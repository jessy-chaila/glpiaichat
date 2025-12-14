<?php

class PluginGlpiaichatConfig extends CommonDBTM {
   public static function getTypeName($nb = 0) {
      return _n('Config chatbot IA', 'Config chatbot IA', $nb, 'glpiaichat');
   }

   public function showConfigForm() {
      global $DB;

      $config = (new PluginGlpiaichatChat())->getConfig();

      echo "<form method='post' action='config.form.php'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2'>Configuration du chatbot IA</th></tr>";

      echo "<tr><td>Numéro de téléphone du support</td><td>";
      Html::autocompletionTextField($this, 'support_phone', ['value' => $config['support_phone'] ?? '']);
      echo "</td></tr>";

      echo "<tr><td>URL API IA</td><td>";
      Html::autocompletionTextField($this, 'ai_api_url', ['value' => $config['ai_api_url'] ?? '']);
      echo "</td></tr>";

      echo "<tr><td>Clé API IA</td><td>";
      Html::autocompletionTextField($this, 'ai_api_key', ['value' => $config['ai_api_key'] ?? '']);
      echo "</td></tr>";

      echo "<tr><td>Prompt système</td><td>";
      echo "<textarea name='system_prompt' cols='60' rows='5'>" .
            Html::entities_deep($config['system_prompt'] ?? '') . "</textarea>";
      echo "</td></tr>";

      echo "<tr><td class='center' colspan='2'>";
      echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
      echo "</td></tr>";

      echo "</table>";
      Html::closeForm();
   }

   public function updateConfig($values) {
      global $DB;

      $support_phone = $DB->escape($values['support_phone'] ?? '');
      $ai_api_url    = $DB->escape($values['ai_api_url'] ?? '');
      $ai_api_key    = $DB->escape($values['ai_api_key'] ?? '');
      $system_prompt = $DB->escape($values['system_prompt'] ?? '');

      $DB->query("UPDATE `glpi_plugin_glpiaichat_configs`
                  SET support_phone = '$support_phone',
                      ai_api_url = '$ai_api_url',
                      ai_api_key = '$ai_api_key',
                      system_prompt = '$system_prompt'
                  WHERE id = 1");
   }
}
