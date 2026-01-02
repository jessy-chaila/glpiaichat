<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directement");
}

require_once __DIR__ . '/providerinterface.class.php';

/**
 * Provider Grok (xAI) pour le plugin glpiaichat.
 *
 * Hypothèse : API compatible OpenAI Chat Completions.
 */
class PluginGlpiaichatXAIProvider implements PluginGlpiaichatProviderInterface {

   /**
    * @param string $systemPrompt
    * @param array  $conversation
    * @param array  $config
    *
    * @return array{assistantText: ?string, error: ?string}
    */
   public function call(string $systemPrompt, array $conversation, array $config): array {
      $url   = $config['ai_api_url'] ?? '';
      $key   = $config['ai_api_key'] ?? '';
      $model = trim((string)($config['ai_model'] ?? ''));

      $openaiMessages = [];

      $openaiMessages[] = [
         'role'    => 'system',
         'content' => $systemPrompt,
      ];

      foreach ($conversation as $t) {
         $openaiMessages[] = [
            'role'    => $t['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $t['content'],
         ];
      }

      $payload = [
         'model'       => $model,
         'messages'    => $openaiMessages,
         'temperature' => 0.2,
         'max_tokens'  => 512,
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
      if (!is_array($decoded) || empty($decoded['choices'][0]['message'])) {
         return [
            'assistantText' => null,
            'error'         => 'format',
         ];
      }

      $msg = $decoded['choices'][0]['message'];

      $assistantText = null;

      if (is_string($msg['content'] ?? null)) {
         $assistantText = $msg['content'];
      } elseif (is_array($msg['content'] ?? null)) {
         $parts = [];
         foreach ($msg['content'] as $part) {
            if (is_string($part)) {
               $parts[] = $part;
            } elseif (is_array($part) && isset($part['text'])) {
               $parts[] = $part['text'];
            }
         }
         $assistantText = implode("\n", $parts);
      }

      return [
         'assistantText' => $assistantText,
         'error'         => null,
      ];
   }

   public function getLabel(): string {
      return 'Grok (xAI)';
   }
}
