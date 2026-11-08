// assets/js/mercure-manager.js
class MercureManager {
    constructor(conferenceId, userId) {
        this.conferenceId = conferenceId;
        this.userId = userId;
        this.eventSource = null;
        this.token = null;
    }

    async initialize() {
        // Récupérer le token JWT
        await this.fetchToken();
        
        // Initialiser la connexion Mercure
        this.connect();
        
        return this.token;
    }

    async fetchToken() {
        try {
            const response = await fetch(`/api/conference/${this.conferenceId}/mercure-token`);
            const data = await response.json();
            
            if (data.token) {
                this.token = data.token;
                console.log('✅ Token Mercure récupéré');
            } else {
                throw new Error('Token non reçu');
            }
        } catch (error) {
            console.error('❌ Erreur récupération token:', error);
            // Fallback: utiliser un token générique
            this.token = await this.getGenericToken();
        }
    }

    async getGenericToken() {
        try {
            const response = await fetch('/api/mercure/token');
            const data = await response.json();
            return data.token;
        } catch (error) {
            console.error('Erreur token générique:', error);
            return null;
        }
    }

    connect() {
        if (!this.token) {
            console.error('❌ Aucun token disponible');
            return;
        }

        const mercureUrl = new URL('{{ mercure_public_url }}');
        mercureUrl.searchParams.append('topic', `/conference/${this.conferenceId}`);
        mercureUrl.searchParams.append('topic', `/user/${this.userId}`);
        mercureUrl.searchParams.append('authorization', this.token);

        this.eventSource = new EventSource(mercureUrl);

        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.onMessage(data);
        };

        this.eventSource.onerror = (error) => {
            console.error('❌ Erreur Mercure:', error);
            this.onError(error);
        };

        this.eventSource.onopen = () => {
            console.log('✅ Connecté à Mercure');
            this.onConnected();
        };
    }

    // Méthodes à override
    onMessage(data) {
        console.log('Message Mercure:', data);
    }

    onError(error) {
        // Reconnexion automatique après 5 secondes
        setTimeout(() => {
            console.log('🔄 Tentative de reconnexion...');
            this.connect();
        }, 5000);
    }

    onConnected() {
        console.log('🔗 Connexion Mercure établie');
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    // Envoyer un signal via l'API Symfony (qui utilisera Mercure avec JWT)
    async sendSignal(signalData) {
        try {
            const response = await fetch(`/conference/${this.conferenceId}/signal`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(signalData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('❌ Erreur envoi signal:', error);
            throw error;
        }
    }
}