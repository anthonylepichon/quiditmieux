/**
 * Description générale : Comportements JavaScript partagés par les pages de l'application.
 * Rôle : Actualiser les comptes à rebours à partir des échéances fournies par le serveur.
 * Tâches : Afficher le temps restant et recharger une seule fois la page lorsqu'une vente se termine.
 * Liens avec les autres fichiers : Est chargé par home.php et utilise les attributs produits par les templates.
 */

let qdmReloadScheduled = false;

/**
 * Rôle : Transformer une durée restante en libellé français lisible.
 * Paramètres : Durée positive exprimée en millisecondes.
 * Retour : Libellé comportant les jours, heures, minutes et secondes.
 */
function qdmFormatRemainingTime(remainingMilliseconds) {
    const totalSeconds = Math.max(0, Math.floor(remainingMilliseconds / 1000));
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return days + ' j ' + hours + ' h ' + minutes + ' min ' + seconds + ' s';
}

/**
 * Rôle : Actualiser tous les comptes à rebours présents dans la page courante.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function qdmUpdateCountdowns() {
    const countdowns = document.querySelectorAll('[data-countdown]');
    const currentTime = Date.now();

    countdowns.forEach(function updateCountdown(countdown) {
        const deadlineValue = countdown.dataset.deadlineUtc;
        const deadlineTime = Date.parse(deadlineValue);

        if (Number.isNaN(deadlineTime)) {
            return;
        }

        const remainingMilliseconds = deadlineTime - currentTime;

        if (remainingMilliseconds > 0) {
            countdown.textContent = qdmFormatRemainingTime(remainingMilliseconds);
            return;
        }

        countdown.textContent = 'Vente terminée';
        countdown.setAttribute('aria-live', 'polite');
        countdown.removeAttribute('data-countdown');

        if (!qdmReloadScheduled) {
            qdmReloadScheduled = true;
            window.setTimeout(function reloadEndedSale() {
                window.location.reload();
            }, 750);
        }
    });
}

qdmUpdateCountdowns();
window.setInterval(qdmUpdateCountdowns, 1000);
