<?php

/**
 * Plugin GLPI AI CHAT - File: swiftaskprovider.class.php
 * Swiftask IA provider implementation for the AI Chat plugin.
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

require_once __DIR__ . '/providerinterface.class.php';

/**
 * Swiftask IA Provider for the glpiaichat plugin.
 *
 * Uses the endpoint:
 * POST https://graphql.swiftask.ai/api/ai/{slug}
 *
 * - The complete URL (including {slug}) is provided via the "URL API IA" configuration.
 * - The Swiftask API key is provided via "Clé API IA".
 * - The "AI Model (ID)" field is not used by Swiftask but must be filled 
 * to pass the plugin's validation logic.
 */
class PluginGlpiaichatSwiftaskProvider implements PluginGlpiaichatProviderInterface {

   /**
    * Calls the Swiftask AI API
    *
    * @param string $systemPrompt System prompt constructed by PluginGlpiaichatChat
    * @param array  $conversation Normalized history: [ ['role'=>'user|assistant','content'=>'...'], ... ]
    * @param array  $config       Plugin config (ai_api_url, ai_api_key, ai_model, ...)
    *
    * @return array{assistantText: ?string, error: ?string}
    */
   public function call(string $systemPrompt, array $conversation, array $config): array {
      $url = $config['ai_api_url'] ?? '';
      $key = $config['ai_api_key'] ?? '';

      // Last user message = "input"
      $input = '';
      for ($i = count($conversation) - 1; $i >= 0; $i--) {
         if (($conversation[$i]['role'] ?? '') === 'user') {
            $input = (string)($conversation[$i]['content'] ?? '');
            break;
         }
      }

      // Construction of messageHistory: system message (as "system"), then user/assistant history
      $messageHistory = [];

      if (trim($systemPrompt) !== '') {
         $messageHistory[] = [
            'role'    => 'system',
            'content' => $systemPrompt,
         ];
      }

      foreach ($conversation as $t) {
         if (!isset($t['role'], $t['content'])) {
            continue;
         }
         $role = ($t['role'] === 'assistant') ? 'assistant' : 'user';
         $messageHistory[] = [
            'role'    => $role,
            'content' => (string)$t['content'],
         ];
      }

      $payload = [
         'input'                => $input,
         'documentAnalysisMode' => 'SIMPLE',
         'files'                => [],
         'messageHistory'       => $messageHistory,
         // sessionId is optional and MUST be a number for Swiftask.
         // We do not send it to avoid validation errors.
      ];

      $ch = curl_init($url);
      curl_setopt_array($ch, [
         CURLOPT_POST           => true,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
         ],
         CURLOPT_POSTFIELDS     => json_encode($payload),
         CURLOPT_TIMEOUT        => 15,
      ]);

      $result = curl_exec($ch);

      if ($result === false) {
         curl_close($ch);
         return [
            'assistantText' => null,
            'error'         => 'communication',
         ];
      }

      curl_close($ch);

      $decoded = json_decode($result, true);
      if (!is_array($decoded)) {
         return [
            'assistantText' => null,
            'error'         => 'format',
         ];
      }

      // If Swiftask returns an error structure, we return a communication error
      if (isset($decoded['error'])) {
         return [
            'assistantText' => null,
            'error'         => 'communication',
         ];
      }

      // According to tests, text is in "text" (and not "$text")
      // We keep a fallback on "$text" just in case Swiftask API changes.
      $assistantText = $decoded['text'] ?? ($decoded['$text'] ?? null);

      if (!is_string($assistantText) || $assistantText === '') {
         return [
            'assistantText' => null,
            'error'         => 'format',
         ];
      }

      return [
         'assistantText' => $assistantText,
         'error'         => null,
      ];
   }

   /**
    * Returns the provider label
    * * @return string
    */
   public function getLabel(): string {
      return __('Swiftask IA', 'glpiaichat');
   }
}
