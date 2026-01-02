<?php

/**
 * Plugin GLPI AI CHAT - File: openaiprovider.class.php
 * ChatGPT (OpenAI) provider implementation for the AI Chat plugin.
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directement");
}

require_once __DIR__ . '/providerinterface.class.php';

/**
 * ChatGPT (OpenAI) Provider for the glpiaichat plugin.
 *
 * Uses an OpenAI Chat Completions compatible API (/v1/chat/completions).
 */
class PluginGlpiaichatOpenAIProvider implements PluginGlpiaichatProviderInterface {

   /**
    * Calls the OpenAI API
    *
    * @param string $systemPrompt Common system prompt
    * @param array  $conversation Normalized history: [ ['role'=>'user|assistant','content'=>'...'], ... ]
    * @param array  $config       Plugin config (ai_api_url, ai_api_key, ai_model, ...)
    *
    * @return array{assistantText: ?string, error: ?string}
    */
   public function call(string $systemPrompt, array $conversation, array $config): array {
      $url   = $config['ai_api_url'] ?? '';
      $key   = $config['ai_api_key'] ?? '';
      $model = trim((string)($config['ai_model'] ?? ''));

      // Construct messages in OpenAI format
      $openaiMessages = [];

      // System message first
      $openaiMessages[] = [
         'role'    => 'system',
         'content' => $systemPrompt,
      ];

      // History + current message
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
         // Some proxies may return an array of segments
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

   /**
    * Returns the provider label
    *
    * @return string
    */
   public function getLabel(): string {
      return __('ChatGPT (OpenAI)', 'glpiaichat');
   }
}
