<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginGlpiaichatChat {

   /**
    * Récupère la configuration du plugin (stockée via Config::setConfigurationValues)
    */
   private function getConfig(): array {
      return Config::getConfigurationValues('glpiaichat', [
         'support_phone',
         'ai_api_url',
         'ai_api_key',
         'system_prompt',
      ]);
   }

   /**
    * Traite un message utilisateur et renvoie la structure attendue par le JS
    */
   public function handleMessage(string $message): array {
      $config = $this->getConfig();

      if (trim($message) === '') {
         return [
            'answer'        => 'Merci de préciser votre question.',
            'needs_ticket'  => false,
            'suggest_call'  => false,
            'support_phone' => $config['support_phone'] ?? null,
            'ticket_title'  => null,
         ];
      }

      // Récupérer l'historique de conversation (session PHP)
      // Format : [ ['role'=>'user','content'=>'...'], ['role'=>'assistant','content'=>'...'], ... ]
      $history = $_SESSION['plugin_glpiaichat_history'] ?? [];

      // Appel IA avec le message courant + historique
      $aiResponse   = $this->callAI($message, $config, $history);
      $answer       = $aiResponse['answer']       ?? 'Je n’ai pas pu générer de réponse.';
      $needs_ticket = (bool) ($aiResponse['needs_ticket'] ?? false);
      $suggest_call = (bool) ($aiResponse['suggest_call'] ?? false);
      $ticket_title = $aiResponse['ticket_title'] ?? null;

      // Mettre à jour l'historique (on stocke uniquement le texte affiché à l'utilisateur)
      $history[] = [
         'role'    => 'user',
         'content' => $message,
      ];
      $history[] = [
         'role'    => 'assistant',
         'content' => $answer,
      ];

      // On garde uniquement les 10 derniers messages (5 tours user/assistant) pour limiter la taille
      $_SESSION['plugin_glpiaichat_history'] = array_slice($history, -10);

      return [
         'answer'        => $answer,
         'needs_ticket'  => $needs_ticket,
         'suggest_call'  => $suggest_call,
         'support_phone' => $config['support_phone'] ?? null,
         'ticket_title'  => $ticket_title,
      ];
   }

   /**
    * Appel au moteur IA (Claude Sonnet via API Anthropic)
    *
    * On attend en retour un JSON strict avec :
    * {
    *   "answer": "texte pour l'utilisateur",
    *   "needs_ticket": true/false,
    *   "suggest_call": true/false,
    *   "ticket_title": "titre court pour le ticket ou \"\" si aucun ticket n'est nécessaire"
    * }
    *
    * $history : historique court de la conversation (user/assistant)
    */
   private function callAI(string $message, array $config, array $history = []): array {
      $url = $config['ai_api_url'] ?? '';
      $key = $config['ai_api_key'] ?? '';

      if ($url === '' || $key === '') {
         return [
            'answer'       => 'Le service d’assistance automatique n’est pas configuré.',
            'needs_ticket' => true,
            'suggest_call' => true,
            'ticket_title' => null,
         ];
      }

      // Nom du modèle Claude Sonnet fourni par ton fournisseur
      $model = 'claude-sonnet-4-5-20250929';

      // Prompt système de base + contexte configurable
      $baseSystemPrompt = <<<TXT
Tu es un assistant de support informatique de niveau 1 intégré à GLPI.
Tu réponds en français, de manière concise et claire.

Tu as accès à l'historique de la conversation (messages précédents).
Tu dois en tenir compte pour enchaîner logiquement : ne recommence pas par un message de bienvenue
si la conversation est déjà en cours.

Ta sortie doit être STRICTEMENT du JSON, sans texte autour, avec le format suivant :

{
  "answer": "réponse texte pour l'utilisateur, en français",
  "needs_ticket": true ou false,
  "suggest_call": true ou false,
  "ticket_title": "titre court pour le ticket ou \"\" si aucun ticket n'est nécessaire"
}

Règles métier :
- "answer" : explique la réponse de manière adaptée à un utilisateur final.
- "needs_ticket" = true si :
  - le problème semble complexe / nécessite une analyse approfondie, OU
  - tu ne peux pas résoudre avec des instructions simples, OU
  - il manque des informations importantes, OU
  - cela touche des droits / accès / pannes globales.
  Sinon "needs_ticket" = false.
- "suggest_call" = true si :
  - la situation est urgente (plus de production, panne totale, sécurité), OU
  - l'utilisateur semble perdu malgré tes explications, OU
  - tu estimes qu'un échange téléphonique serait plus efficace.
  Sinon "suggest_call" = false.

- "ticket_title" :
  - Si "needs_ticket" = true, tu DOIS générer un titre COURT et CLAIR qui résume le problème,
    par exemple :
      - "Problème d'export PDF avec Alizée"
      - "Blocage à l'ouverture de Outlook"
      - "Impossible d'imprimer sur l'imprimante BOCCA"
  - Le titre ne doit pas contenir de phrase entière, pas de "Bonjour", pas de tournure polie.
  - Pas de numéro de ticket, pas de date, pas de mention du mot "ticket".
  - Si "needs_ticket" = false, tu mets "ticket_title" = "" (chaîne vide).

Ne mets AUCUN autre champ dans le JSON.
Ne mets pas de ```json``` ni aucune autre balise de code.
Renvoie UNIQUEMENT le JSON brut, sans aucun texte ou formatage autour.
N'utilise pas de commentaires.
Respecte strictement le JSON valide.
TXT;

      $systemPrompt = $baseSystemPrompt;
      if (!empty($config['system_prompt'])) {
         $systemPrompt .= "\n\nContexte supplémentaire fourni par le client :\n" . $config['system_prompt'];
      }

      // Construire la liste des messages pour l'API Anthropic à partir de l'historique
      $messages = [];

      foreach ($history as $turn) {
         if (!isset($turn['role'], $turn['content'])) {
            continue;
         }
         $role = ($turn['role'] === 'assistant') ? 'assistant' : 'user';
         $content = (string)$turn['content'];

         if (trim($content) === '') {
            continue;
         }

         $messages[] = [
            'role'    => $role,
            'content' => $content,
         ];
      }

      // Ajouter le message courant de l'utilisateur
      $messages[] = [
         'role'    => 'user',
         'content' => $message,
      ];

      $payload = [
         'model'       => $model,
         'max_tokens'  => 512,
         'temperature' => 0.2,
         'system'      => $systemPrompt,
         'messages'    => $messages,
      ];

      $ch = curl_init($url);
      curl_setopt_array($ch, [
         CURLOPT_POST           => true,
         CURLOPT_RETURNTRANSFER => true,
         CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
         ],
         CURLOPT_POSTFIELDS     => json_encode($payload),
         CURLOPT_TIMEOUT        => 15,
      ]);

      $result = curl_exec($ch);
      if ($result === false) {
         curl_close($ch);
         return [
            'answer'       => 'Erreur de communication avec le moteur IA.',
            'needs_ticket' => true,
            'suggest_call' => true,
            'ticket_title' => null,
         ];
      }
      curl_close($ch);

      $decoded = json_decode($result, true);
      if (!is_array($decoded)) {
         return [
            'answer'       => 'Réponse IA invalide (format non JSON).',
            'needs_ticket' => true,
            'suggest_call' => true,
            'ticket_title' => null,
         ];
      }

      // Anthropic renvoie le contenu dans content[0].text
      $assistantText = '';
      if (isset($decoded['content'][0]['text'])) {
         $assistantText = $decoded['content'][0]['text'];
      } elseif (isset($decoded['content'][0]['type'])
                && $decoded['content'][0]['type'] === 'text'
                && isset($decoded['content'][0]['text'])) {
         $assistantText = $decoded['content'][0]['text'];
      }

      $assistantText = trim($assistantText);

      if ($assistantText === '') {
         return [
            'answer'       => 'Le moteur IA n’a renvoyé aucun contenu.',
            'needs_ticket' => true,
            'suggest_call' => true,
            'ticket_title' => null,
         ];
      }

      // 1er essai : parse direct le texte renvoyé comme JSON
      $json = json_decode($assistantText, true);

      // Si ça échoue, on tente de retirer d'éventuels ```json ... ```
      if (!is_array($json)) {
         if (preg_match('~```(?:json)?\s*(\{.*\})\s*```~s', $assistantText, $m)) {
            $clean = trim($m[1]);
            $tmp   = json_decode($clean, true);
            if (is_array($tmp)) {
               $json          = $tmp;
               $assistantText = $clean;
            }
         }
      }

      if (!is_array($json)) {
         // Fallback si le modèle ne respecte vraiment pas le format :
         // on affiche le texte brut et on force ticket + appel
         return [
            'answer'       => $assistantText,
            'needs_ticket' => true,
            'suggest_call' => true,
            'ticket_title' => null,
         ];
      }

      $title = null;
      if (isset($json['ticket_title']) && is_string($json['ticket_title'])) {
         $t = trim($json['ticket_title']);
         if ($t !== '') {
            $title = $t;
         }
      }

      return [
         'answer'       => $json['answer']       ?? $assistantText,
         'needs_ticket' => (bool)($json['needs_ticket'] ?? false),
         'suggest_call' => (bool)($json['suggest_call'] ?? false),
         'ticket_title' => $title,
      ];
   }

   /**
    * Crée un ticket GLPI à partir de la question/réponse du chatbot
    *
    * @param string      $question        Historique concaténé des messages utilisateur
    * @param string      $answer          (ignoré ici, gardé pour compat)
    * @param string|null $preferredTitle  Titre proposé par l'IA (ticket_title)
    */
   public function createTicketFromChat(string $question, string $answer, ?string $preferredTitle = null): array {
      $question       = trim($question);
      $preferredTitle = trim((string)$preferredTitle);

      // Si l'IA a proposé un titre, on le privilégie
      $title = '';

      if ($preferredTitle !== '') {
         $title = $preferredTitle;

         // Tronquer pour éviter les titres énormes
         if (mb_strlen($title, 'UTF-8') > 120) {
            $title = mb_substr($title, 0, 117, 'UTF-8') . '...';
         }

      } else {
         // Sinon, on retombe sur la logique basée sur les messages utilisateur
         $rawLines = preg_split("/\r\n|\n|\r/u", $question);
         $lines = [];

         foreach ($rawLines as $line) {
            $line = trim($line);
            if ($line !== '') {
               $lines[] = $line;
            }
         }

         if (empty($lines)) {
            $title = 'Demande via chatbot IA';
         } else {
            // Déterminer la ligne qui servira de base au titre
            $titleLine = $lines[0];

            // Essayer de sauter les salutations pour le titre ("bonjour", "salut", etc.)
            foreach ($lines as $line) {
               $low = mb_strtolower($line, 'UTF-8');

               // salutations courtes à ignorer comme titre
               $isGreeting = preg_match('/^(bonjour|bonsoir|salut|hello|coucou|bjr|bjs)\b/u', $low);
               if ($isGreeting && mb_strlen($low, 'UTF-8') <= 40) {
                  continue;
               }

               // Sinon, on prend cette ligne comme base de titre
               $titleLine = $line;
               break;
            }

            // Extraire la première phrase du titre (jusqu'à ., ? ou !)
            $separators = "/(\.|\?|!)/u";
            $parts = preg_split($separators, $titleLine, 2, PREG_SPLIT_DELIM_CAPTURE);
            if (!empty($parts[0])) {
               $title = trim($parts[0]);
            } else {
               $title = trim($titleLine);
            }

            // Tronquer pour éviter les titres énormes
            if (mb_strlen($title, 'UTF-8') > 120) {
               $title = mb_substr($title, 0, 117, 'UTF-8') . '...';
            }

            if ($title === '') {
               $title = 'Demande via chatbot IA';
            }
         }
      }

      // Construction du contenu avec tous les messages utilisateur numérotés
      $rawLines = preg_split("/\r\n|\n|\r/u", $question);
      $lines = [];

      foreach ($rawLines as $line) {
         $line = trim($line);
         if ($line !== '') {
            $lines[] = $line;
         }
      }

      if (empty($lines)) {
         $content = "Conversation utilisateur (via chatbot IA) :\n\n" . $question;
      } else {
         $formattedLines = [];
         foreach ($lines as $idx => $line) {
            $num = $idx + 1;
            $formattedLines[] = "Message {$num} de l'utilisateur :\n{$line}";
         }

         $content = "Conversation utilisateur (via chatbot IA) :\n\n" . implode("\n\n", $formattedLines);
      }

      $ticket = new Ticket();

      $input = [
         'name'               => $title,
         'content'            => $content,
         'users_id_recipient' => Session::getLoginUserID(),
         'entities_id'        => $_SESSION['glpiactive_entity'] ?? 0,
         'status'             => Ticket::INCOMING,
      ];

      if ($ticket_id = $ticket->add($input)) {
         return [
            'success'   => true,
            'ticket_id' => $ticket_id,
            'title'     => $title,
         ];
      }

      return ['success' => false];
   }
}
