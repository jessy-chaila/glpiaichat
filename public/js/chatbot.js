/**
 * Plugin GLPI AI CHAT - File: public/js/chatbot.js
 * Client-side logic for the AI Chatbot: UI initialization, message handling, and ticket creation.
 */

document.addEventListener('DOMContentLoaded', function () {
  if (typeof CFG_GLPI === 'undefined') {
    return;
  }

  // -------------------------------------------------------------------
  // 1. UI Configuration (icon, color, mode)
  // -------------------------------------------------------------------
  let uiConfig = {
    mode: 'degraded',
    bot_icon_type: 'emoji',
    bot_icon_text: '?',
    bot_icon_image_url: '',
    bot_color: '',
    bot_color_use_theme: true
  };

  /**
   * Fetches the plugin UI configuration from the server.
   */
  async function fetchUiConfig() {
    try {
      const url = CFG_GLPI.root_doc + '/plugins/glpiaichat/ajax.chat.php?action=get_config';
      const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin'
      });
      const textResp = await response.text();
      const data = JSON.parse(textResp);

      if (data && typeof data === 'object') {
        uiConfig = {
          mode: data.mode || 'degraded',
          bot_icon_type: data.bot_icon_type || 'emoji',
          bot_icon_text: data.bot_icon_text || '?',
          bot_icon_image_url: data.bot_icon_image_url || '',
          bot_color: data.bot_color || '',
          bot_color_use_theme: (typeof data.bot_color_use_theme === 'boolean'
                                 ? data.bot_color_use_theme
                                 : true)
        };
      }
    } catch (e) {
      console.error('glpiaichat ui config error', e);
      // Fallback to default configuration on error
    }
  }

  // -------------------------------------------------------------------
  // 2. User Information
  // -------------------------------------------------------------------
  let userDisplayName = 'Vous';
  let userInitials    = 'VO';

  /**
   * Fetches current logged-in user info for message display.
   */
  async function fetchUserInfo() {
    try {
      const url = CFG_GLPI.root_doc + '/plugins/glpiaichat/ajax.chat.php?action=get_user';
      const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin'
      });
      const textResp = await response.text();
      const data = JSON.parse(textResp);
      if (data.name) {
        userDisplayName = data.name;
      }
      if (data.initials) {
        userInitials = data.initials;
      }
    } catch (e) {
      console.error('glpiaichat user info error', e);
      // Keep default values on error
    }
  }

  // -------------------------------------------------------------------
  // 3. History Reset (performed once per page load)
  // -------------------------------------------------------------------
  let historyResetDone = false;

  /**
   * Clears the session chat history when the chat window is first opened.
   */
  async function resetHistoryIfNeeded() {
    if (historyResetDone) {
      return;
    }
    historyResetDone = true;
    try {
      const url = CFG_GLPI.root_doc + '/plugins/glpiaichat/ajax.chat.php?action=reset_history';
      await fetch(url, {
        method: 'GET',
        credentials: 'same-origin'
      });
    } catch (e) {
      console.error('glpiaichat reset history error', e);
    }
  }

  // -------------------------------------------------------------------
  // 4. Global Initialization
  // -------------------------------------------------------------------
  (async function initChatbot() {
    await Promise.all([fetchUiConfig(), fetchUserInfo()]);

    // Apply custom primary color if configured in full mode
    if (uiConfig.mode === 'full' && !uiConfig.bot_color_use_theme && uiConfig.bot_color) {
      try {
        document.documentElement.style.setProperty('--glpiaichat-primary', uiConfig.bot_color);
        document.documentElement.style.setProperty('--glpiaichat-user-bg', uiConfig.bot_color);
        document.documentElement.style.setProperty('--glpiaichat-primary-dark', uiConfig.bot_color);
      } catch (e) {
        console.error('glpiaichat color apply error', e);
      }
    }

    // ----------------------------------------------------------------
    // Floating Bubble Creation
    // ----------------------------------------------------------------
    const bubble = document.createElement('div');
    bubble.id = 'glpiaichat-bubble';

    // Bubble icon handling (Image vs Emoji/Text)
    if (uiConfig.mode === 'full' &&
        uiConfig.bot_icon_type === 'image' &&
        uiConfig.bot_icon_image_url) {

      const iconImage = document.createElement('img');
      iconImage.src = uiConfig.bot_icon_image_url;
      iconImage.alt = 'Bot';
      iconImage.style.width = '100%';
      iconImage.style.height = '100%';
      iconImage.style.borderRadius = '999px';
      iconImage.style.objectFit = 'cover';
      bubble.appendChild(iconImage);

    } else {
      const text = (uiConfig.mode === 'full')
        ? (uiConfig.bot_icon_text || '?')
        : '?';
      bubble.textContent = text;
    }

    document.body.appendChild(bubble);

    // ----------------------------------------------------------------
    // Chat Window Creation
    // ----------------------------------------------------------------
    const win = document.createElement('div');
    win.id = 'glpiaichat-window';
    win.innerHTML = `
      <div class="glpiaichat-header">
        <div class="glpiaichat-header-left">
          <div class="glpiaichat-header-avatar">IA</div>
          <div>
            <div class="glpiaichat-header-title">Assistant GLPI</div>
            <div class="glpiaichat-header-subtitle">Support niveau 1</div>
          </div>
        </div>
        <button class="glpiaichat-header-close" title="Fermer">×</button>
      </div>
      <div class="glpiaichat-messages" id="glpiaichat-messages"></div>
      <div class="glpiaichat-footer">
        <div class="glpiaichat-input-wrapper">
          <textarea
            id="glpiaichat-input"
            class="glpiaichat-input"
            rows="1"
            placeholder="Décrivez votre problème..."
          ></textarea>
          <button id="glpiaichat-send" class="glpiaichat-send" title="Envoyer">➤</button>
        </div>
      </div>
    `;
    document.body.appendChild(win);

    const messagesDiv = win.querySelector('#glpiaichat-messages');
    const textarea    = win.querySelector('#glpiaichat-input');
    const sendBtn     = win.querySelector('#glpiaichat-send');
    const closeBtn    = win.querySelector('.glpiaichat-header-close');

    let welcomeShown = false;
    let ticketAlreadyCreated = false;

    /**
     * Appends a message to the chat window UI.
     * @param {string} from - 'user' or 'ia'
     * @param {string} text - Message content
     * @param {string} extraHTML - Optional HTML for action buttons
     */
    function appendMessage(from, text, extraHTML = '') {
      const wrapper = document.createElement('div');
      wrapper.className = 'glpiaichat-message ' + (from === 'user'
        ? 'glpiaichat-message-user'
        : 'glpiaichat-message-ia');

      const avatarWrapper = document.createElement('div');
      avatarWrapper.className = 'glpiaichat-avatar-wrapper';

      const avatar = document.createElement('div');
      avatar.className = 'glpiaichat-avatar ' + (from === 'user'
        ? 'glpiaichat-avatar-user'
        : 'glpiaichat-avatar-ia');
      avatar.textContent = (from === 'user') ? userInitials : 'IA';

      avatarWrapper.appendChild(avatar);

      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'glpiaichat-content-wrapper';

      const nameDiv = document.createElement('div');
      nameDiv.className = 'glpiaichat-name';
      nameDiv.textContent = (from === 'user') ? userDisplayName : 'Assistant';

      const inner = document.createElement('div');
      inner.className = 'glpiaichat-message-inner';

      const safeText = text || '';
      inner.innerHTML = safeText.replace(/\n/g, '<br>')
        + (extraHTML ? `<div class="glpiaichat-actions">${extraHTML}</div>` : '');

      contentWrapper.appendChild(nameDiv);
      contentWrapper.appendChild(inner);

      wrapper.appendChild(avatarWrapper);
      wrapper.appendChild(contentWrapper);

      messagesDiv.appendChild(wrapper);
      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    /**
     * Shows initial welcome message if it hasn't been displayed yet.
     */
    function showWelcomeIfNeeded() {
      if (welcomeShown) {
        return;
      }
      appendMessage(
        'ia',
        "Bonjour, cet assistant vous aide à formuler et suivre vos demandes GLPI. Décrivez votre problème et il vous proposera des vérifications simples ou l’ouverture d’un ticket si nécessaire."
      );
      welcomeShown = true;
    }

    // Toggle window visibility on bubble click
    bubble.addEventListener('click', () => {
      const isHidden = (win.style.display === 'none' || win.style.display === '');
      win.style.display = isHidden ? 'flex' : 'none';
      if (isHidden) {
        win.classList.remove('glpiaichat-open');
        void win.offsetWidth; // Trigger reflow for animation
        win.classList.add('glpiaichat-open');
        resetHistoryIfNeeded();
        showWelcomeIfNeeded();
        textarea.focus();
      }
    });

    closeBtn.addEventListener('click', () => {
      win.style.display = 'none';
    });

    /**
     * Resizes the input textarea dynamically based on content.
     */
    function autoResizeTextarea() {
      textarea.style.height = 'auto';
      textarea.style.height = Math.min(textarea.scrollHeight, 90) + 'px';
    }

    textarea.addEventListener('input', autoResizeTextarea);

    /**
     * Aggregates all user messages from the current conversation for ticket content.
     * @returns {string} Concatenated user messages.
     */
    function collectUserMessages() {
      const userNodes = messagesDiv.querySelectorAll('.glpiaichat-message-user .glpiaichat-message-inner');
      const msgs = [];
      userNodes.forEach(node => {
        const txt = node.innerText.trim();
        if (txt) {
          msgs.push(txt);
        }
      });
      return msgs.join('\n');
    }

    /**
     * Basic HTML escaping utility for titles.
     * @param {string} str 
     */
    function escapeHtml(str) {
      return String(str).replace(/[&<>"']/g, s => {
        switch (s) {
          case '&': return '&amp;';
          case '<': return '&lt;';
          case '>': return '&gt;';
          case '"': return '&quot;';
          case '\'': return '&#39;';
          default: return s;
        }
      });
    }

    /**
     * Sends the user message to the server-side AI handler.
     */
    async function sendMessage() {
      const text = textarea.value.trim();
      if (!text) return;

      appendMessage('user', text);
      textarea.value = '';
      autoResizeTextarea();

      const typing = document.createElement('div');
      typing.className = 'glpiaichat-typing';
      typing.textContent = 'L’assistant réfléchit...';
      messagesDiv.appendChild(typing);
      messagesDiv.scrollTop = messagesDiv.scrollHeight;

      try {
        const url = CFG_GLPI.root_doc
          + '/plugins/glpiaichat/ajax.chat.php?message='
          + encodeURIComponent(text);

        const response = await fetch(url, {
          method: 'GET',
          credentials: 'same-origin'
        });

        const textResp = await response.text();
        messagesDiv.removeChild(typing);

        let data;
        try {
          data = JSON.parse(textResp);
        } catch (e) {
          console.error('glpiaichat JSON parse error', e, textResp);
          appendMessage('ia', 'Erreur lors de la communication avec le serveur (réponse invalide).');
          return;
        }

        if (data.error) {
          appendMessage('ia', 'Erreur : ' + data.error);
          return;
        }

        let extra = '';

        // Add action buttons if AI suggests a ticket or call
        if (data.needs_ticket) {
          if (!ticketAlreadyCreated) {
            extra += `<button class="glpiaichat-btn glpiaichat-btn-primary glpiaichat-open-ticket">Ouvrir un ticket</button>`;
          }
          if (data.support_phone) {
            extra += ` <button class="glpiaichat-btn glpiaichat-call-support">Appeler le support : ${data.support_phone}</button>`;
          }
        }

        appendMessage('ia', data.answer || '(Aucune réponse)', extra);

        if (data.needs_ticket) {
          const suggestedTitle = data.ticket_title || '';

          if (!ticketAlreadyCreated) {
            messagesDiv
              .querySelectorAll('.glpiaichat-open-ticket')
              .forEach(btn => {
                btn.addEventListener('click', () => {
                  const allUserText = collectUserMessages();
                  openTicket(allUserText, suggestedTitle);
                });
              });
          }

          messagesDiv
            .querySelectorAll('.glpiaichat-call-support')
            .forEach(btn => {
              btn.addEventListener('click', () => {
                // Potential integration for telephony systems
              });
            });
        }

      } catch (e) {
        if (typing.parentNode) {
          messagesDiv.removeChild(typing);
        }
        appendMessage('ia', 'Erreur lors de la communication avec le serveur.');
        console.error('glpiaichat error', e);
      }
    }

    /**
     * Triggers the GLPI ticket creation process via AJAX.
     * @param {string} questionHistory - Aggregated user input
     * @param {string} suggestedTitle - AI-suggested ticket title
     */
    async function openTicket(questionHistory, suggestedTitle) {
      try {
        const url = CFG_GLPI.root_doc
          + '/plugins/glpiaichat/ajax.chat.php'
          + '?action=create_ticket'
          + '&question=' + encodeURIComponent(questionHistory)
          + '&answer='
          + '&title=' + encodeURIComponent(suggestedTitle || '');

        const response = await fetch(url, {
          method: 'GET',
          credentials: 'same-origin'
        });

        const textResp = await response.text();

        let data;
        try {
          data = JSON.parse(textResp);
        } catch (e) {
          console.error('glpiaichat JSON parse error (ticket)', e, textResp);
          appendMessage('ia', 'Erreur lors de la création du ticket (réponse invalide).');
          return;
        }

        if (data.success) {
          const ticketId  = data.ticket_id;
          const realTitle = data.title || ('Ticket #' + ticketId);
          const url       = CFG_GLPI.root_doc + '/front/ticket.form.php?id=' + ticketId;
          const safeTitle = escapeHtml(realTitle);

          ticketAlreadyCreated = true;
          // Remove "Open Ticket" buttons after successful creation
          messagesDiv.querySelectorAll('.glpiaichat-open-ticket').forEach(btn => {
            btn.remove();
          });

          appendMessage(
            'ia',
            `Ticket créé : <a href="${url}" target="_blank">${safeTitle} (#${ticketId})</a>`
          );
        } else {
          appendMessage('ia', 'Impossible de créer le ticket automatiquement.');
        }
      } catch (e) {
        appendMessage('ia', 'Erreur lors de la création du ticket.');
        console.error('glpiaichat ticket error', e);
      }
    }

    sendBtn.addEventListener('click', sendMessage);

    textarea.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  })();
});
