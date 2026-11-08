// assets/js/video-conference.js
(function () {
    // Avoid redeclaring the class if the script is loaded multiple times
    if (typeof window !== 'undefined' && typeof window.VideoConference !== 'undefined') {
        // Reuse existing implementation
        return;
    }

    class VideoConference {
        constructor(conferenceId, currentUserId) {
            this.conferenceId = conferenceId;
            this.currentUserId = currentUserId;

            // Managers (assumed to be available in the environment)
            this.mercureManager = new MercureManager(this.conferenceId, this.currentUserId);
            this.webrtcManager = new WebRTCManager(this.conferenceId, this.currentUserId, this.mercureManager);

            // Start initialization
            this.initializeConference();
        }

        async initializeConference() {
            try {
                console.log('🚀 Initialisation de la conférence...');

                // Initialiser Mercure
                await this.mercureManager.initialize();

                // Override des gestionnaires d'événements Mercure
                this.mercureManager.onMessage = (data) => this.handleMercureMessage(data);
                this.mercureManager.onConnected = () => this.onMercureConnected();

                // Initialiser WebRTC
                await this.webrtcManager.initialize();

                // Configurer les callbacks WebRTC
                this.webrtcManager.onParticipantsUpdate = (participants) => {
                    this.handleParticipantsUpdate(participants);
                };

                // Ajouter notre vidéo locale
                this.addLocalVideoToGrid();

                // Configurer les événements UI
                this.setupEventListeners();
                this.updateControlButtons();

                console.log('✅ Conférence initialisée avec succès');

            } catch (error) {
                console.error('❌ Erreur initialisation:', error);
                this.showError('Impossible de démarrer la conférence: ' + (error.message || error));
            }
        }

        handleMercureMessage(data) {
            console.log('📨 Message Mercure:', data);

            switch (data.type) {
                case 'user-joined':
                    this.handleUserJoined && this.handleUserJoined(data);
                    break;
                case 'user-left':
                    this.handleUserLeft && this.handleUserLeft(data);
                    break;
                case 'offer':
                    if (data.to === this.currentUserId || data.to === 'all') {
                        this.handleWebRTCOffer && this.handleWebRTCOffer(data.from, data.sdp);
                    }
                    break;
                case 'answer':
                    if (data.to === this.currentUserId || data.to === 'all') {
                        this.handleWebRTCAnswer && this.handleWebRTCAnswer(data.from, data.sdp);
                    }
                    break;
                case 'ice-candidate':
                    if (data.to === this.currentUserId || data.to === 'all') {
                        this.handleWebRTCIceCandidate && this.handleWebRTCIceCandidate(data.from, data.candidate);
                    }
                    break;
                case 'participants-update':
                    // If Mercure sends a participants update payload
                    if (data.participants) {
                        this.handleParticipantsUpdate(data.participants);
                    }
                    break;
            }
        }

        onMercureConnected() {
            // Notifier que j'ai rejoint
            this.mercureManager.sendSignal({
                type: 'user-joined',
                to: 'all'
            });
        }

        async sendWebRTCSignal(type, to, data = {}) {
            const signalData = {
                type: type,
                to: to,
                ...data
            };

            return await this.mercureManager.sendSignal(signalData);
        }

        leaveConference() {
            // Notifier que je quitte
            this.mercureManager.sendSignal({
                type: 'user-left',
                to: 'all'
            });

            // Déconnecter Mercure
            this.mercureManager.disconnect && this.mercureManager.disconnect();

            // Stop WebRTC local streams and peers if manager exposes stop
            this.webrtcManager.stop && this.webrtcManager.stop();

            // Rediriger
            window.location.href = '/conference';
        }

        /* ==== Participant UI handling ==== */

        handleParticipantsUpdate(participants) {
            console.log('🔄 Mise à jour des participants:', participants);

            // Mettre à jour l'interface utilisateur
            this.updateParticipantsList(participants);
            this.updateParticipantCount(participants.length);
        }

        updateParticipantsList(participants) {
            const participantsList = document.getElementById('participants-list');
            if (!participantsList) return;

            participantsList.innerHTML = '';

            participants.forEach(participant => {
                const participantItem = this.createParticipantListItem(participant);
                participantsList.appendChild(participantItem);
            });
        }

        updateParticipantCount(count) {
            const countElement = document.getElementById('live-participant-count');
            if (countElement) {
                countElement.textContent = count;
            }
        }

        createParticipantListItem(participant) {
            const item = document.createElement('div');
            item.className = `participant-item ${participant.role || ''}`;

            const nameSafe = participant.name || 'Inconnu';
            const statusDotClass = !participant.has_microphone ? 'muted' : '';

            item.innerHTML = `
                <div class="participant-avatar">
                    ${nameSafe.charAt(0).toUpperCase()}
                </div>
                <div class="participant-details">
                    <div class="name">${this.escapeHtml(nameSafe)}</div>
                    <div class="role">${this.getRoleLabel(participant.role)}</div>
                </div>
                <div class="participant-status-indicators">
                    <div class="status-dot ${statusDotClass}"></div>
                    ${participant.is_hand_raised ? '<i class="fas fa-hand-paper text-warning"></i>' : ''}
                </div>
            `;

            return item;
        }

        getRoleLabel(role) {
            switch (role) {
                case 'host':
                    return 'Hôte';
                case 'moderator':
                    return 'Modérateur';
                case 'speaker':
                    return 'Intervenant';
                default:
                    return 'Participant';
            }
        }

        /* ==== UI helpers & events ==== */

        addLocalVideoToGrid() {
            try {
                const container = document.getElementById('local-video-container') || document.getElementById('video-grid');
                if (!container) return;

                // Create or reuse local video element
                let localVideo = document.getElementById('local-video');
                if (!localVideo) {
                    localVideo = document.createElement('video');
                    localVideo.id = 'local-video';
                    localVideo.autoplay = true;
                    localVideo.muted = true; // mute local playback
                    localVideo.playsInline = true;
                    container.appendChild(localVideo);
                }

                // Attach local stream if available from webrtcManager
                if (this.webrtcManager && this.webrtcManager.localStream) {
                    try {
                        localVideo.srcObject = this.webrtcManager.localStream;
                    } catch (e) {
                        // fallback to src if needed
                        localVideo.src = URL.createObjectURL(this.webrtcManager.localStream);
                    }
                }
            } catch (e) {
                console.warn('Erreur lors de l\'ajout de la vidéo locale:', e);
            }
        }

        setupEventListeners() {
            // Leave button
            const leaveBtn = document.getElementById('leave-conference-btn');
            if (leaveBtn) {
                leaveBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.leaveConference();
                });
            }

            // Mute/unmute
            const muteBtn = document.getElementById('toggle-mic-btn');
            if (muteBtn) {
                muteBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (this.webrtcManager && typeof this.webrtcManager.toggleAudio === 'function') {
                        this.webrtcManager.toggleAudio();
                        this.updateControlButtons();
                    }
                });
            }

            // Raise hand (example)
            const raiseHandBtn = document.getElementById('raise-hand-btn');
            if (raiseHandBtn) {
                raiseHandBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    // signal raise hand to others via Mercure
                    this.mercureManager.sendSignal({
                        type: 'hand-raised',
                        to: 'all',
                        from: this.currentUserId
                    });
                });
            }
        }

        updateControlButtons() {
            const muteBtn = document.getElementById('toggle-mic-btn');
            if (muteBtn && this.webrtcManager) {
                const micEnabled = this.webrtcManager.isAudioEnabled ? !!this.webrtcManager.isAudioEnabled() : true;
                muteBtn.textContent = micEnabled ? 'Couper le micro' : 'Activer le micro';
            }
        }

        showError(message) {
            console.error(message);
            const errorEl = document.getElementById('conference-error');
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            } else {
                // fallback alert
                // eslint-disable-next-line no-alert
                alert(message);
            }
        }

        escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function (m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[m];
            });
        }
    }

    if (typeof window !== 'undefined') {
        window.VideoConference = VideoConference;
    } else {
        // ensure availability in other environments
        globalThis.VideoConference = VideoConference;
    }
})();