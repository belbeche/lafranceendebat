// assets/js/webrtc-manager.js

class WebRTCManager {
    constructor() {
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.reconnectDelay = 2000;
        this.conferenceId = null;
        this.isConnected = false;
        this.heartbeatInterval = null;
    }

    async initialize(conferenceId, options = {}) {
        console.log('🎯 Initialisation WebRTC...');
        this.conferenceId = conferenceId;
        this.options = options;

        try {
            await this.connectSSE(conferenceId);
            this.startHeartbeat();
            console.log('✅ WebRTC initialisé avec succès');
            return true;
        } catch (error) {
            console.error('❌ Erreur initialisation WebRTC:', error);
            this.handleFatalError(error);
            return false;
        }
    }

    connectSSE(conferenceId) {
        return new Promise((resolve, reject) => {
            if (this.eventSource) {
                this.eventSource.close();
            }

            const url = `/conference/${conferenceId}/stream-simple`;
            console.log('🔗 Connexion SSE à:', url);

            this.eventSource = new EventSource(url);

            this.eventSource.onopen = ()
        video: true 
    }) {
        console.log('🎥 Initialisation avec média...', constraints);
        
        try {
            // D'abord initialiser la connexion SSE
            await this.initialize(conferenceId);
            
            // Puis accéder aux médias
            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            console.log('✅ Accès média réussi', this.localStream);
            
            return this.localStream;
            
        } catch (error) {
            console.error('❌ Erreur initialisation avec média:', error);
            throw error;
        }
    }

    /**
     * Connexion SSE
     */
    connectSSE(conferenceId) {
        return new Promise((resolve, reject) => {
            try {
                const url = `/conference/${conferenceId}/stream-simple`;
                console.log('🔗 Connexion à:', url);
                
                this.eventSource = new EventSource(url);
                
                this.eventSource.onopen = (event) => {
                    console.log('✅ Connexion SSE établie');
                    this.reconnectAttempts = 0;
                    resolve();
                };
                
                this.eventSource.onmessage = (event) => {
                    try {
                        if (event.data.trim() === ': heartbeat') {
                            console.log('💓 Battement SSE reçu');
                            return;
                        }
                        
                        const data = JSON.parse(event.data);
                        console.log('📨 Message reçu:', data);
                        this.handleSSEMessage(data);
                    } catch (error) {
                        console.error('❌ Erreur parsing message:', error);
                    }
                };
                
                this.eventSource.onerror = (event) => {
                    console.error('❌ Erreur SSE:', event);
                    
                    if (this.eventSource.readyState === EventSource.CLOSED) {
                        this.handleReconnection(conferenceId);
                    }
                    reject(event);
                };
                
                // Timeout de sécurité
                setTimeout(() => {
                    if (this.eventSource.readyState !== EventSource.OPEN) {
                        reject(new Error('Timeout connexion SSE'));
                    }
                }, 10000);
                
            } catch (error) {
                console.error('❌ Erreur création SSE:', error);
                reject(error);
            }
        });
    }

    /**
     * Gestion des messages SSE
     */
    handleSSEMessage(data) {
        switch (data.type) {
            case 'connected':
                console.log('🎉 Connexion signalisation établie', data);
                this.loadParticipants();
                break;
                
            case 'participants_update':
                console.log('👥 Participants mis à jour:', data.participants);
                this.updateParticipants(data.participants);
                break;
                
            case 'webrtc_offer':
                this.handleOffer(data);
                break;
                
            case 'webrtc_answer':
                this.handleAnswer(data);
                break;
                
            case 'ice_candidate':
                this.handleICECandidate(data);
                break;
                
            default:
                console.log('📨 Message non géré:', data);
        }
    }

    /**
     * Chargement des participants
     */
    async loadParticipants() {
        try {
            console.log('📥 Chargement des participants...');
            
            const response = await fetch(`/conference/${this.conferenceId}/participants`);
            const participants = await response.json();
            
            console.log('👥 Participants chargés:', participants);
            this.updateParticipants(participants);
            
        } catch (error) {
            console.error('❌ Erreur chargement participants:', error);
        }
    }

    /**
     * Mise à jour de l'interface participants
     */
    updateParticipants(participants) {
        // Déclencher un événement personnalisé
        const event = new CustomEvent('participantsUpdated', {
            detail: { participants }
        });
        document.dispatchEvent(event);
        
        // Ou utiliser une callback si définie
        if (this.options.onParticipantsUpdate) {
            this.options.onParticipantsUpdate(participants);
        }
    }

    /**
     * Gestion reconnexion
     */
    handleReconnection(conferenceId) {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            this.reconnectDelay = Math.min(this.reconnectDelay * 1.5, 30000);
            
            console.log(`🔄 Reconnexion ${this.reconnectAttempts}/${this.maxReconnectAttempts} dans ${this.reconnectDelay}ms`);
            
            setTimeout(() => {
                this.disconnectSSE();
                this.connectSSE(conferenceId).catch(console.error);
            }, this.reconnectDelay);
        } else {
            console.error('🚨 Échec reconnexion après', this.maxReconnectAttempts, 'tentatives');
            this.showConnectionError();
        }
    }

    /**
     * Déconnexion SSE
     */
    disconnectSSE() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    /**
     * Arrêt complet
     */
    destroy() {
        this.disconnectSSE();
        
        // Arrêter tous les streams
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
        }
        
        // Fermer toutes les connexions peer
        this.peerConnections.forEach(pc => pc.close());
        this.peerConnections.clear();
        
        this.isInitialized = false;
        console.log('🔴 WebRTCManager détruit');
    }

    /**
     * Gestion audio
     */
    toggleAudio() {
        if (!this.localStream) return;
        
        const audioTrack = this.localStream.getAudioTracks()[0];
        if (audioTrack) {
            audioTrack.enabled = !audioTrack.enabled;
            console.log('🎤 Audio:', audioTrack.enabled ? 'activé' : 'désactivé');
            return audioTrack.enabled;
        }
        return false;
    }

    /**
     * Gestion vidéo
     */
    toggleVideo() {
        if (!this.localStream) return;
        
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (videoTrack) {
            videoTrack.enabled = !videoTrack.enabled;
            console.log('📹 Vidéo:', videoTrack.enabled ? 'activée' : 'désactivée');
            return videoTrack.enabled;
        }
        return false;
    }

    /**
     * Affichage erreur connexion
     */
    showConnectionError() {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3';
        errorDiv.style.zIndex = '9999';
        errorDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-wifi-slash me-2"></i>
                <div>
                    <strong>Problème de connexion</strong>
                    <p class="mb-0">La connexion au serveur a été perdue.</p>
                </div>
                <button class="btn btn-sm btn-outline-danger ms-3" onclick="location.reload()">
                    <i class="fas fa-redo me-1"></i>Rafraîchir
                </button>
            </div>
        `;
        document.body.appendChild(errorDiv);
        
        // Auto-suppression après 10s
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 10000);
    }
}

// Export pour utilisation globale
window.WebRTCManager = WebRTCManager;