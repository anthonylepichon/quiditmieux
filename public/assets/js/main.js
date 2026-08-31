/**
 * Description générale : Comportements JavaScript partagés par les pages de l'application.
 * Rôle : Gérer les comportements partagés de navigation et de compte à rebours.
 * Tâches : Actualiser les échéances et rendre le menu principal utilisable sur petit écran.
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
 * Rôle : Décomposer une durée restante en quatre valeurs numériques.
 * Paramètres : Durée positive exprimée en millisecondes.
 * Retour : Tableau contenant les jours, heures, minutes et secondes.
 */
function qdmRemainingTimeParts(remainingMilliseconds) {
    const totalSeconds = Math.max(0, Math.floor(remainingMilliseconds / 1000));

    return [
        Math.floor(totalSeconds / 86400),
        Math.floor((totalSeconds % 86400) / 3600),
        Math.floor((totalSeconds % 3600) / 60),
        totalSeconds % 60
    ];
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
            const valuesContainer = countdown.querySelector('[data-countdown-values]');

            if (valuesContainer !== null) {
                const valueElements = valuesContainer.querySelectorAll('span');
                const timeParts = qdmRemainingTimeParts(remainingMilliseconds);

                valueElements.forEach(function updateTimePart(valueElement, index) {
                    const label = valueElement.querySelector('small');
                    let labelText = '';

                    if (label !== null) {
                        labelText = label.textContent;
                    }

                    valueElement.textContent = String(timeParts[index]).padStart(2, '0');

                    if (labelText !== '') {
                        const restoredLabel = document.createElement('small');
                        restoredLabel.textContent = labelText;
                        valueElement.append(restoredLabel);
                    }
                });
            } else {
                countdown.textContent = qdmFormatRemainingTime(remainingMilliseconds);
            }

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

/**
 * Rôle : Ouvrir ou fermer la navigation compacte.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function qdmInitializeMenu() {
    const menuButton = document.querySelector('[data-menu-button]');
    const menuPanel = document.querySelector('[data-menu-panel]');

    if (menuButton === null || menuPanel === null) {
        return;
    }

    menuButton.addEventListener('click', function toggleMenu() {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';

        menuButton.setAttribute('aria-expanded', String(!isOpen));
        menuPanel.classList.toggle('site-header__navigation--open', !isOpen);
    });
}

qdmInitializeMenu();
qdmUpdateCountdowns();
window.setInterval(qdmUpdateCountdowns, 1000);
