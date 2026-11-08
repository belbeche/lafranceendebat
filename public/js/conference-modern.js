// assets/js/conference-modern.js

class ConferenceModern {
    constructor() {
        this.webrtcManager = new WebRTCManager();
        this.conferenceId = null;
        this.isJoined = false;
    }

    /**
     * Initialise la conférence
     */
    async initialize(conferenceId, options = {}) {
        console.log('🚀 Démarrage de la conférence moderne...', { conferenceId, options });
        
        this.conferenceId = conferenceId;
        
        try {
            // Initialiser sans média d'abord
            await this.webrtcManager.initialize(conferenceId, {
                onParticipantsUpdate: (participants) => this.updateVideoGrid(participants),
                ...options
            });
            
            console.log('✅ Conférence moderne initialisée avec succès (sans média)');
            return true;
            
        } catch (error) {
            console.error('❌ Erreur initialisation conférence:', error);
            this.showError('Impossible de démarrer la conférence: ' + error.message);
            return false;
        }
    }

    /**
     * Rejoindre avec média
     */
    async joinWithMedia(constraints = { audio: true, video: true }) {
        console.log('🎥 Rejoindre avec média...', constraints);
        
        if (!this.webrtcManager.isInitialized) {
            throw new Error('WebRTCManager non initialisé');
        }
        
        try {
            const localStream = await this.webrtcManager.initializeWithMedia(this.conferenceId, constraints);
            this.isJoined = true;
            
            // Afficher le stream local
            this.displayLocalStream(localStream);
            
            console.log('✅ Rejoint avec média avec succès');
            return localStream;
            
        } catch (error) {
            console.error('❌ Erreur rejoindre avec média:', error);
            throw error;
        }
    }

    /**
     * Afficher le stream local
     */
    displayLocalStream(stream) {
        const videoGrid = document.getElementById('videoGrid');
        if (!videoGrid) {
            console.warn('📺 Container videoGrid non trouvé');
            return;
        }
        
        // Créer ou mettre à jour l'élément vidéo local
        let localVideo = document.getElementById('localVideo');
        if (!localVideo) {
            localVideo = document.createElement('video');
            localVideo.id = 'localVideo';
            localVideo.autoplay = true;
            localVideo.muted = true;
            localVideo.playsInline = true;
            localVideo.className = 'video-participant local-video';
            videoGrid.appendChild(localVideo);
        }
        
        localVideo.srcObject = stream;
        console.log('📹 Stream local affiché');
    }

    /**
     * Mettre à jour la grille vidéo
     */
    updateVideoGrid(participants) {
        console.log('📊 Mise à jour grille vidéo avec', participants.length, 'participants');
        
        const videoGrid = document.getElementById('videoGrid');
        if (!videoGrid) {
            console.warn('📺 Container videoGrid non trouvé');
            return;
        }
        
        // Logique de mise à jour de la grille vidéo
        // ... votre code existant ...
    }

    /**
     * Quitter la conférence
     */
    leave() {
        console.log('👋 Quitter la conférence');
        
        this.webrtcManager.destroy();
        this.isJoined = false;
        
        // Nettoyer l'interface
        this.cleanupVideoGrid();
    }

    /**
     * Nettoyer la grille vidéo
     */
    cleanupVideoGrid() {
        const videoGrid = document.getElementById('videoGrid');
        if (videoGrid) {
            videoGrid.innerHTML = '';
        }
    }

    /**
     * Basculer audio
     */
    toggleAudio() {
        if (!this.isJoined) return;
        return this.webrtcManager.toggleAudio();
    }

    /**
     * Basculer vidéo
     */
    toggleVideo() {
        if (!this.isJoined) return;
        return this.webrtcManager.toggleVideo();
    }

    /**
     * Afficher une erreur
     */
    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
        errorDiv.innerHTML = `
            <strong>Erreur</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Ajouter au début du body
        document.body.insertBefore(errorDiv, document.body.firstChild);
        
        // Auto-suppression après 5s
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
}

// Initialisation globale
window.ConferenceModern = ConferenceModern;

// Auto-initialisation si data-conference-id présent
document.addEventListener('DOMContentLoaded', function() {
    const conferenceElement = document.querySelector('[data-conference-id]');
    if (conferenceElement) {
        const conferenceId = conferenceElement.dataset.conferenceId;
        window.currentConference = new ConferenceModern();
        window.currentConference.initialize(conferenceId);
    }
});