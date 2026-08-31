/**
 * Description générale : Interactions AJAX du détail d'une annonce.
 * Rôle : Modifier le suivi volontaire sans perdre le repli HTML classique.
 * Tâches : Verrouiller le formulaire, envoyer le POST, appliquer la réponse serveur et annoncer le résultat.
 * Liens avec les autres fichiers : Est chargé par listing-detail.php et appelle ParticipationController.php.
 */

const qdmParticipationStatus = document.querySelector('[data-participation-status]');

/**
 * Rôle : Construire l'adresse AJAX d'un formulaire sans modifier son action HTML de repli.
 * Paramètres : Formulaire concerné.
 * Retour : Adresse demandant une réponse JSON.
 */
function qdmJsonAction(form) {
    const url = new URL(form.action, window.location.href);
    url.searchParams.set('format', 'json');
    return url.toString();
}

/**
 * Rôle : Mettre à jour le formulaire de suivi selon l'état confirmé par le serveur.
 * Paramètres : Formulaire et données JSON validées.
 * Retour : Aucun.
 */
function qdmUpdateFollowForm(form, data) {
    const button = form.querySelector('button');
    const actionUrl = new URL(form.action, window.location.href);

    if (data.is_following) {
        actionUrl.searchParams.set('route', 'unfollow_listing');
        button.textContent = 'Ne plus suivre';
    } else {
        actionUrl.searchParams.set('route', 'follow_listing');
        button.textContent = 'Suivre';
    }

    form.action = actionUrl.pathname + '?' + actionUrl.searchParams.toString();
}

/**
 * Rôle : Envoyer une demande de suivi et conserver l'état visible en cas d'échec réseau.
 * Paramètres : Événement de soumission.
 * Retour : Aucun.
 */
async function qdmSubmitFollow(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button');
    button.disabled = true;

    try {
        const response = await fetch(qdmJsonAction(form), {
            method: 'POST',
            body: new FormData(form),
            headers: {'Accept': 'application/json'}
        });
        const data = await response.json();

        if (!data || typeof data.success !== 'boolean' || typeof data.message !== 'string') {
            qdmParticipationStatus.textContent = 'La réponse reçue ne permet pas d’actualiser le suivi.';
            return;
        }

        qdmParticipationStatus.textContent = data.message;

        if (data.success) {
            qdmUpdateFollowForm(form, data);
        }
    } catch (error) {
        qdmParticipationStatus.textContent = 'Le suivi n’a pas pu être actualisé. Utilisez de nouveau le bouton.';
    } finally {
        button.disabled = false;
    }
}

document.querySelectorAll('[data-follow-form]').forEach(function prepareFollowForm(form) {
    form.addEventListener('submit', qdmSubmitFollow);
});
