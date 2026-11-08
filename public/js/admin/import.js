// Fonctions d'affichage des messages
function showSuccess(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('.container').prepend(alertDiv);
    
    // Auto-dismiss après 5 secondes
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function showError(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('.container').prepend(alertDiv);
}

function showWarning(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-warning alert-dismissible fade show';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('.container').prepend(alertDiv);
}

// Fonction pour mettre à jour la progression
function updateProgress(data) {
    // Mettre à jour la barre de progression
    const progressBar = document.getElementById('import-progress-bar');
    const progressText = document.getElementById('import-progress-text');
    const progressDetails = document.getElementById('import-progress-details');
    
    if (progressBar) {
        progressBar.style.width = `${data.progress}%`;
        progressBar.setAttribute('aria-valuenow', data.progress);
        progressBar.textContent = `${data.progress}%`;
    }
    
    if (progressText) {
        progressText.textContent = data.message || `Progression: ${data.progress}%`;
    }
    
    if (progressDetails) {
        progressDetails.innerHTML = `
            <div class="row mt-2">
                <div class="col-md-3">
                    <small class="text-muted">Total: ${data.total}</small>
                </div>
                <div class="col-md-3">
                    <small class="text-success">Importés: ${data.imported}</small>
                </div>
                <div class="col-md-3">
                    <small class="text-danger">Échoués: ${data.failed}</small>
                </div>
                <div class="col-md-3">
                    <small class="text-warning">Ignorés: ${data.skipped}</small>
                </div>
            </div>
        `;
    }
    
    // Mettre à jour le statut
    const statusBadge = document.getElementById('import-status-badge');
    if (statusBadge) {
        statusBadge.textContent = data.status;
        statusBadge.className = `badge bg-${getStatusColor(data.status)}`;
    }
}

function getStatusColor(status) {
    const colors = {
        'pending': 'secondary',
        'processing': 'info',
        'completed': 'success',
        'failed': 'danger',
        'cancelled': 'warning',
        'stopped': 'dark',
        'stuck': 'danger'
    };
    return colors[status] || 'secondary';
}

// Fonction principale pour démarrer l'import
async function startImport(importLogId) {
    console.log('Démarrage de l\'import ID:', importLogId);
    
    // Désactiver le bouton de démarrage
    const startButton = document.getElementById('start-import-btn');
    if (startButton) {
        startButton.disabled = true;
        startButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Démarrage en cours...';
    }
    
    // Récupérer le token CSRF du DOM
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
        || document.querySelector('input[name="_token"]')?.value
        || document.querySelector('[name="csrf_token"]')?.value;
    
    if (!csrfToken) {
        console.error('Token CSRF non trouvé');
        showError('Token de sécurité non trouvé. Veuillez rafraîchir la page.');
        return;
    }
    
    console.log('Token CSRF récupéré:', csrfToken.substring(0, 20) + '...');
    
    // Récupérer les données du formulaire
    const data = {
        _token: csrfToken,
        campaign_mode: document.getElementById('campaign_mode')?.value || 'new',
        campaign_name: document.getElementById('campaign_name')?.value || '',
        skip_duplicates: document.getElementById('skip_duplicates')?.checked || true,
        has_header: document.getElementById('has_header')?.checked || true,
        delimiter: document.getElementById('delimiter')?.value || ',',
        campaign_date: document.getElementById('campaign_date')?.value || null,
        campaign_source: document.getElementById('campaign_source')?.value || null,
        campaign_tags: document.getElementById('campaign_tags')?.value || null
    };
    
    console.log('Données envoyées:', data);
    
    try {
        const response = await fetch(`/admin/import/start/${importLogId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        console.log('Réponse HTTP:', response.status, result);
        
        if (!response.ok) {
            throw new Error(`Erreur HTTP ${response.status}: ${result.error || 'Unknown error'}`);
        }
        
        if (result.success) {
            console.log('Import démarré avec succès:', result);
            showSuccess('Import démarré avec succès!');
            
            // Afficher la section de progression
            const progressSection = document.getElementById('import-progress-section');
            if (progressSection) {
                progressSection.classList.remove('d-none');
            }
            
            // Démarrer le polling de progression
            startProgressPolling(importLogId);
            
        } else {
            throw new Error(result.error || 'Erreur inconnue');
        }
        
    } catch (error) {
        console.error('Erreur démarrage import:', error);
        showError('Erreur démarrage import: ' + error.message);
        
        // Réactiver le bouton
        if (startButton) {
            startButton.disabled = false;
            startButton.innerHTML = '<i class="fas fa-play"></i> Démarrer l\'import';
        }
    }
}

// Fonction pour démarrer le polling de progression
function startProgressPolling(importLogId) {
    let pollInterval;
    let attempts = 0;
    const maxAttempts = 120; // 10 minutes max (5s * 120)
    
    function poll() {
        if (attempts >= maxAttempts) {
            clearInterval(pollInterval);
            showError('Import timeout après 10 minutes');
            return;
        }
        
        attempts++;
        
        fetch(`/admin/import/progress/${importLogId}?_=${Date.now()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateProgress(data);
                    
                    // Si terminé, arrêter le polling
                    if (data.completed) {
                        clearInterval(pollInterval);
                        
                        // Réactiver le bouton pour redémarrer si nécessaire
                        const startButton = document.getElementById('start-import-btn');
                        if (startButton) {
                            startButton.disabled = false;
                            startButton.innerHTML = '<i class="fas fa-redo"></i> Redémarrer';
                            startButton.onclick = () => resetAndRestartImport(importLogId);
                        }
                        
                        // Afficher le message final
                        if (data.status === 'completed') {
                            showSuccess('Import terminé avec succès!');
                        } else if (data.status === 'failed') {
                            showError('Import échoué');
                        } else {
                            showWarning(`Import terminé avec statut: ${data.status}`);
                        }
                        
                        // Afficher le bouton pour voir les résultats
                        const resultsSection = document.getElementById('import-results-section');
                        if (resultsSection) {
                            resultsSection.classList.remove('d-none');
                        }
                    }
                } else {
                    throw new Error(data.error || 'Erreur inconnue');
                }
            })
            .catch(error => {
                console.error('Erreur polling:', error);
                // Continuer à poller malgré les erreurs temporaires
            });
    }
    
    // Poller toutes les 5 secondes
    poll();
    pollInterval = setInterval(poll, 5000);
}

// Fonction pour réinitialiser et redémarrer un import
async function resetAndRestartImport(importLogId) {
    if (!confirm('Voulez-vous vraiment réinitialiser et redémarrer cet import ?')) {
        return;
    }
    
    try {
        const response = await fetch(`/admin/import/reset-status/${importLogId}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Import réinitialisé. Redémarrage...');
            
            // Masquer les résultats
            const resultsSection = document.getElementById('import-results-section');
            if (resultsSection) {
                resultsSection.classList.add('d-none');
            }
            
            // Redémarrer l'import après un court délai
            setTimeout(() => startImport(importLogId), 1000);
        } else {
            throw new Error(result.error || 'Erreur de réinitialisation');
        }
    } catch (error) {
        showError('Erreur lors de la réinitialisation: ' + error.message);
    }
}

// Fonction pour annuler un import
async function cancelImport(importLogId) {
    if (!confirm('Voulez-vous vraiment annuler cet import ?')) {
        return;
    }
    
    try {
        const response = await fetch(`/admin/import/cancel/${importLogId}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Import annulé avec succès');
            
            // Recharger la page après 2 secondes
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(result.error || 'Erreur lors de l\'annulation');
        }
    } catch (error) {
        showError('Erreur lors de l\'annulation: ' + error.message);
    }
}

// Fonction pour arrêter un import
async function stopImport(importLogId) {
    if (!confirm('Voulez-vous vraiment arrêter cet import ?')) {
        return;
    }
    
    try {
        const response = await fetch(`/admin/import/stop/${importLogId}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Import arrêté avec succès');
            
            // Mettre à jour l'interface
            const startButton = document.getElementById('start-import-btn');
            if (startButton) {
                startButton.disabled = false;
                startButton.innerHTML = '<i class="fas fa-redo"></i> Reprendre';
            }
        } else {
            throw new Error(result.error || 'Erreur lors de l\'arrêt');
        }
    } catch (error) {
        showError('Erreur lors de l\'arrêt: ' + error.message);
    }
}

// Initialisation quand la page est chargée
document.addEventListener('DOMContentLoaded', function() {
    console.log('Import script loaded');
    
    // Vérifier s'il y a un import en cours sur cette page
    const importLogIdElement = document.getElementById('import-log-id');
    if (importLogIdElement) {
        const importLogId = importLogIdElement.value;
        console.log('Import ID détecté:', importLogId);
        
        // Vérifier l'état de l'import existant
        checkExistingImport(importLogId);
    }
    
    // Gérer les événements de formulaire
    const startButton = document.getElementById('start-import-btn');
    if (startButton) {
        startButton.addEventListener('click', function(e) {
            e.preventDefault();
            const importLogId = document.getElementById('import-log-id').value;
            startImport(importLogId);
        });
    }
    
    // Gérer le changement de mode de campagne
    const campaignModeSelect = document.getElementById('campaign_mode');
    if (campaignModeSelect) {
        campaignModeSelect.addEventListener('change', function() {
            const mode = this.value;
            
            // Masquer/Afficher les champs en fonction du mode
            document.getElementById('new-campaign-fields')?.classList.toggle('d-none', mode !== 'new');
            document.getElementById('existing-campaign-fields')?.classList.toggle('d-none', mode !== 'existing');
            document.getElementById('import-campaign-fields')?.classList.toggle('d-none', mode !== 'import');
        });
        
        // Déclencher l'événement pour l'état initial
        campaignModeSelect.dispatchEvent(new Event('change'));
    }
});

// Fonction pour vérifier un import existant
async function checkExistingImport(importLogId) {
    try {
        const response = await fetch(`/admin/import/progress/${importLogId}?_=${Date.now()}`);
        
        if (response.ok) {
            const data = await response.json();
            
            if (data.success && !data.completed && data.status === 'processing') {
                // Un import est déjà en cours
                showWarning('Un import est déjà en cours pour cet ID.');
                
                // Afficher la section de progression
                const progressSection = document.getElementById('import-progress-section');
                if (progressSection) {
                    progressSection.classList.remove('d-none');
                }
                
                // Démarrer le polling
                startProgressPolling(importLogId);
                
                // Désactiver le bouton de démarrage
                const startButton = document.getElementById('start-import-btn');
                if (startButton) {
                    startButton.disabled = true;
                    startButton.innerHTML = '<i class="fas fa-sync fa-spin"></i> Import en cours...';
                }
            }
        }
    } catch (error) {
        console.log('Aucun import en cours ou erreur de vérification:', error);
    }
}

// Exporter les fonctions pour une utilisation globale
window.ImportManager = {
    startImport,
    cancelImport,
    stopImport,
    resetAndRestartImport,
    startProgressPolling
};