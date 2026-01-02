<?php

/**
 * Plugin GLPI AI CHAT - File: geminiprovider.class.php
 * Gemini (Google) provider implementation for the AI Chat plugin.
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directement");
}

require_once __DIR__ . '/providerinterface.class.php';

/**
 * Gemini (Google) Provider for the glpiaichat plugin.
 *
 * This class handles:
 * - Payload construction for the Gemini API (generateContent)
 * - HTTP call (cURL)
 * - Extraction of the assistant's text from the Gemini response
 *
 * It DOES NOT interpret the "business" JSON (answer / needs_ticket / ...),
 * as that task is handled in PluginGlpiaichatChat::callAI().
 */
class PluginGlpiaichatGeminiProvider implements PluginGlpiaichatProviderInterface {

   /**
    * Calls the Gemini API (Google)
    *
    * @param string $systemPrompt Common system prompt
    * @param array  $conversation Normalized history: [ ['role'=>'user|assistant','content'=>'...'], ... ]
    * @param array  $config       Plugin config (ai_api_url, ai_api_key, ai_model, ...)
    *
    * @return array{assistantText: ?string, error: ?string}
    */
   public function call(string $systemPrompt, array $conversation, array $config): array {
      $url = $config['ai_api_url'] ?? '';
      $key = $config['ai_api_key'] ?? '';
      // $model is currently unused as the Gemini URL usually includes the model ID
      $model = trim((string)($config['ai_model'] ?? ''));

      // Construct "contents" in Gemini format
      $contents = [];
      foreach ($conversation as $t) {
         $contents[] = [
            'role'  => ($t['role'] === 'assistant') ? 'model' : 'user',
            'parts' => [
               ['text' => $t['content']],
            ],
         ];
      }

      $payload = [
         'contents'           => $contents,
         'system_instruction' => [
            'role'  => 'system',
            'parts' => [
               ['text' => $systemPrompt],
            ],
         ],
         'generationConfig'   => [
            'temperature'     => 0.2,
            'maxOutputTokens' => 512,
         ],
      ];

      $ch = curl_init($url);
      curl_setopt_array($ch, [
         CURLOPT_POST           => true,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $key,
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

      // Handle non-JSON response
      if (!is_array($decoded)) {
         return [
            'assistantText' => null,
            'error'         => 'format',
         ];
      }

      // Explicit handling of errors returned by the Gemini API
      if (isset($decoded['error']['message'])) {
         $apiErrorMessage = (string)$decoded['error']['message'];

         return [
            // Return message so caller can display it
            'assistantText' => $apiErrorMessage,
            'error'         => 'api_error',
         ];
      }

      // Standard case: expect candidates[0].content.parts[0].text
      if (empty($decoded['candidates'][0]['content']['parts'][0]['text'])) {
         return [
            'assistantText' => null,
            'error'         => 'format',
         ];
      }

      $assistantText = $decoded['candidates'][0]['content']['parts'][0]['text'];

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
      return __('Gemini (Google)', 'glpiaichat');
   }
}
