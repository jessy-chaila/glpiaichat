GLPI AI Chatbot Plugin

Réduisez la charge de travail de votre support technique en automatisant les réponses aux demandes à faible valeur ajoutée.

Ce plugin intègre une bulle de conversation interactive alimentée par l'Intelligence Artificielle directement dans l'interface de GLPI. Il agit comme un premier niveau de support pour filtrer les incidents simples (ex: "ma souris ne marche plus", "comment nettoyer mon écran") sans solliciter vos techniciens.

🚀 Fonctionnalités Clés

Support de Premier Niveau IA : Réponses non techniques et pédagogiques pour les utilisateurs finaux.

Escalade Intelligente : Si l'IA ne peut pas résoudre le problème ou si le contexte devient complexe, le bot propose automatiquement de créer un ticket.

Contextualisation des Tickets : Les tickets générés incluent l'historique complet de la conversation avec un titre pertinent généré par l'IA.

Assistance Hybride : Possibilité d'afficher les coordonnées téléphoniques du service informatique en cas de besoin.

Hautement Configurable :

Multi-LLM : Choix de l'IA, du modèle, de l'URL du point de terminaison et gestion sécurisée de la clé API.

Personnalisation du Comportement : Définissez un "System Prompt" personnalisé pour aligner le ton du bot sur la culture de votre entreprise.

UI Customisation : Modifiez l'apparence de la bulle (couleurs, icônes) directement depuis l'interface GLPI.

🛠️ Configuration

Le plugin offre uneinterface d'administration complète pour piloter l'IA :

Connexion IA : Compatible avec les API standards (OpenAI, Azure, instances locales comme Ollama/vLLM).

Prompt Système : Un comportement de base est codé en dur pour garantir la sécurité, mais vous pouvez ajouter vos propres instructions métier.

Design : Sélecteur de couleurs et options d'affichage pour une intégration visuelle parfaite à votre thème GLPI.

📋 Prérequis

GLPI 11.0 ou supérieur.

PHP 8.2+.

Une clé API valide pour le service d'IA de votre choix.

💻 Installation
Clonez ce dépôt dans votre répertoire plugins/ :

cd /var/www/glpi/plugins

git clone https://github.com/jessy-chaila/glpiaichat/

Allez dans Configuration > Plugins.

Cliquez sur Installer puis sur Activer.

🛡️ Sécurité & Éthique

Le bot est conçu pour :

Ne jamais proposer d'actions nécessitant des droits administrateur.

Rester dans un cadre de réponse simplifié et non technique.

Ne pas exécuter de commandes système.
