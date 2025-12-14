document.addEventListener('DOMContentLoaded', function () {
  if (typeof CFG_GLPI === 'undefined') {
    return;
  }

  // Infos utilisateur (remplies via AJAX)
  let userDisplayName = 'Vous';
  let userInitials    = 'VO';

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
      // on garde les valeurs par défaut
    }
  }

  // Lancer la récupération des infos utilisateur (asynchrone, sans bloquer l'UI)
  fetchUserInfo();

  // Gestion du reset d'historique (une fois par page)
  let historyResetDone = false;

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

  // Bulle flottante
  const bubble = document.createElement('div');
  bubble.id = 'glpiaichat-bubble';
  bubble.textContent = '?'; // icône de la bulle (changeable)
  document.body.appendChild(bubble);

  // Fenêtre principale
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

  function appendMessage(from, text, extraHTML = '') {
    const wrapper = document.createElement('div');
    wrapper.className = 'glpiaichat-message ' + (from === 'user'
      ? 'glpiaichat-message-user'
      : 'glpiaichat-message-ia');

    // Avatar + label
    const avatarWrapper = document.createElement('div');
    avatarWrapper.className = 'glpiaichat-avatar-wrapper';

    const avatar = document.createElement('div');
    avatar.className = 'glpiaichat-avatar ' + (from === 'user'
      ? 'glpiaichat-avatar-user'
      : 'glpiaichat-avatar-ia');
    avatar.textContent = (from === 'user') ? userInitials : 'IA';

    avatarWrapper.appendChild(avatar);

    // Colonne contenu (nom + bulle)
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

    // Ajout à la ligne
    wrapper.appendChild(avatarWrapper);
    wrapper.appendChild(contentWrapper);

    messagesDiv.appendChild(wrapper);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
  }

  function showWelcomeIfNeeded() {
    if (welcomeShown) {
      return;
    }
    appendMessage(
      'ia',
      "Bonjour, je suis votre assistant de support GLPI. Décrivez-moi votre problème et je vous proposerai des vérifications simples ou j’ouvrirai un ticket si nécessaire."
    );
    welcomeShown = true;
  }

  // Ouverture / fermeture
  bubble.addEventListener('click', () => {
    const isHidden = (win.style.display === 'none' || win.style.display === '');
    win.style.display = isHidden ? 'flex' : 'none';
    if (isHidden) {
      win.classList.remove('glpiaichat-open'); // reset animation
      // forcer reflow pour réappliquer l'animation
      void win.offsetWidth;
      win.classList.add('glpiaichat-open');
      // Nouvelle "session de chat" côté backend pour cette page
      resetHistoryIfNeeded();
      showWelcomeIfNeeded();
      textarea.focus();
    }
  });

  closeBtn.addEventListener('click', () => {
    win.style.display = 'none';
  });

  function autoResizeTextarea() {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 90) + 'px';
  }

  textarea.addEventListener('input', autoResizeTextarea);

  // Récupérer toutes les interventions de l'utilisateur dans la conversation
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

  // Petit helper pour échapper le HTML quand on injecte un titre
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

  async function sendMessage() {
    const text = textarea.value.trim();
    if (!text) return;

    appendMessage('user', text);
    textarea.value = '';
    autoResizeTextarea();

    // Indicateur "IA tape..."
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

      // Quand l'IA propose un ticket, on ajoute aussi un bouton "Appeler le support"
      if (data.needs_ticket) {
        extra += `<button class="glpiaichat-btn glpiaichat-btn-primary glpiaichat-open-ticket">Ouvrir un ticket</button>`;
        if (data.support_phone) {
          extra += ` <button class="glpiaichat-btn glpiaichat-call-support">Appeler le support : ${data.support_phone}</button>`;
        }
      }

      appendMessage('ia', data.answer || '(Aucune réponse)', extra);

      if (data.needs_ticket) {
        const suggestedTitle = data.ticket_title || '';

        // Bouton ouverture de ticket
        messagesDiv
          .querySelectorAll('.glpiaichat-open-ticket')
          .forEach(btn => {
            btn.addEventListener('click', () => {
              const allUserText = collectUserMessages();
              openTicket(allUserText, suggestedTitle);
            });
          });

        // Bouton appel support (ouvre XiVO dans un nouvel onglet)
        messagesDiv
          .querySelectorAll('.glpiaichat-call-support')
          .forEach(btn => {
            btn.addEventListener('click', () => {
              window.open('https://xivo.brestaim.fr', '_blank');
            });
          });
      }

    } catch (e) {
      messagesDiv.removeChild(typing);
      appendMessage('ia', 'Erreur lors de la communication avec le serveur.');
      console.error('glpiaichat error', e);
    }
  }

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
});
