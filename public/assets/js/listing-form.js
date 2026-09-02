/**
 * Description générale : Gestion locale des photographies du formulaire d'annonce.
 * Rôle : Cumuler et prévisualiser les fichiers choisis avant l'envoi classique.
 * Tâches : Conserver les sélections successives, contrôler leur nombre, leur type et leur taille puis permettre leur retrait.
 * Liens avec les autres fichiers : Est chargé par listing-form.php sans appel AJAX.
 */

const qdmPhotoInput = document.querySelector('[data-photo-input]');
const qdmPhotoPreviews = document.querySelector('[data-photo-previews]');
const qdmPhotoStatus = document.querySelector('[data-photo-status]');
const qdmPhotoTitle = document.querySelector('[data-photo-title]');
const qdmPhotoCounter = document.querySelector('[data-photo-counter]');
const qdmPhotoTileStatus = document.querySelector('[data-photo-tile-status]');
const qdmExistingPhotoRemovalInputs = document.querySelectorAll('input[name="remove_photos[]"]');
const qdmListingForm = document.querySelector('[data-listing-form]');
let qdmIsEditMode = false;
let qdmSelectedPhotos = [];

if (qdmListingForm && qdmListingForm.querySelector('input[name="id"]')) {
    qdmIsEditMode = true;
}

/**
 * Rôle : Compter les photographies existantes qui seront conservées.
 * Paramètres : Aucun.
 * Retour : Nombre de photographies existantes non cochées pour retrait.
 */
function qdmActiveExistingPhotoCount() {
    let activeCount = 0;

    qdmExistingPhotoRemovalInputs.forEach(function countActivePhoto(input) {
        if (input instanceof HTMLInputElement && !input.checked) {
            activeCount += 1;
        }
    });

    return activeCount;
}

/**
 * Rôle : Indiquer si un fichier respecte les règles ergonomiques avant validation serveur.
 * Paramètres : Fichier sélectionné.
 * Retour : true si le fichier est acceptable, sinon false.
 */
function qdmIsAcceptedPhoto(file) {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    return allowedTypes.includes(file.type) && file.size <= 5 * 1024 * 1024;
}

/**
 * Rôle : Supprimer les aperçus précédents et libérer leurs adresses temporaires.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function qdmClearPhotoPreviews() {
    if (!qdmPhotoPreviews) {
        return;
    }

    qdmPhotoPreviews.querySelectorAll('img').forEach(function revokePreview(image) {
        if (image.src.startsWith('blob:')) {
            URL.revokeObjectURL(image.src);
        }
    });
    qdmPhotoPreviews.replaceChildren();
}

/**
 * Rôle : Construire un identifiant local stable pour repérer une même photographie.
 * Paramètres : Fichier sélectionné.
 * Retour : Chaîne composée de ses propriétés disponibles dans le navigateur.
 */
function qdmPhotoIdentifier(file) {
    return file.name + '|' + file.size + '|' + file.type + '|' + file.lastModified;
}

/**
 * Rôle : Reporter la liste cumulée dans le champ de fichiers réellement envoyé au serveur.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function qdmSynchronizePhotoInput() {
    const transfer = new DataTransfer();

    qdmSelectedPhotos.forEach(function addPhotoToTransfer(file) {
        transfer.items.add(file);
    });

    qdmPhotoInput.files = transfer.files;
}

/**
 * Rôle : Actualiser le message annonçant le nombre de photographies prêtes.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function qdmUpdatePhotoStatus() {
    const activeExistingPhotoCount = qdmActiveExistingPhotoCount();
    const totalPhotoCount = activeExistingPhotoCount + qdmSelectedPhotos.length;

    if (qdmPhotoCounter) {
        qdmPhotoCounter.textContent = totalPhotoCount + ' / 3';
    }

    if (qdmPhotoTileStatus) {
        if (activeExistingPhotoCount === 0) {
            qdmPhotoTileStatus.textContent = totalPhotoCount + ' / 3 — la première ajoutée sera principale';
        } else {
            qdmPhotoTileStatus.textContent = totalPhotoCount + ' / 3';
        }
    }

    if (qdmPhotoTitle) {
        if (totalPhotoCount === 0) {
            qdmPhotoTitle.textContent = 'Aucune photographie ajoutée';
        } else if (qdmIsEditMode) {
            qdmPhotoTitle.textContent = 'Gestion des photographies';
        } else {
            qdmPhotoTitle.textContent = 'Photographies ajoutées';
        }
    }

    if (totalPhotoCount >= 3) {
        qdmPhotoStatus.textContent = 'Capacité atteinte. Supprimez une photo pour en ajouter une autre. La suivante devient principale si la première est supprimée.';
        return;
    }

    if (totalPhotoCount === 0) {
        qdmPhotoStatus.textContent = 'Vous pouvez publier sans photo ou en ajouter jusqu’à trois.';
        return;
    }

    if (qdmIsEditMode) {
        qdmPhotoStatus.textContent = 'Ajoutez, remplacez ou supprimez les photographies dans la limite de trois.';
        return;
    }

    qdmPhotoStatus.textContent = 'La première photographie ajoutée est automatiquement l’image principale.';
}

/**
 * Rôle : Construire les aperçus locaux dans l'ordre réel d'envoi.
 * Paramètres : Liste de fichiers validés localement.
 * Retour : Aucun.
 */
function qdmRenderPhotoPreviews(files) {
    qdmClearPhotoPreviews();
    const activeExistingPhotoCount = qdmActiveExistingPhotoCount();

    files.forEach(function renderPhoto(file, index) {
        const article = document.createElement('article');
        const image = document.createElement('img');
        const label = document.createElement('span');
        const removeButton = document.createElement('button');
        article.className = 'photo-preview photo-preview--new';
        image.src = URL.createObjectURL(file);
        image.alt = 'Aperçu de ' + file.name;
        label.textContent = 'Photo ' + (activeExistingPhotoCount + index + 1);
        removeButton.className = 'button button--secondary button--compact';
        removeButton.type = 'button';
        removeButton.textContent = 'Supprimer';
        removeButton.setAttribute('aria-label', 'Supprimer ' + file.name);

        if (activeExistingPhotoCount === 0 && index === 0) {
            label.textContent += ' — principale';
        }

        removeButton.addEventListener('click', function removeSelectedPhoto() {
            qdmSelectedPhotos.splice(index, 1);
            qdmSynchronizePhotoInput();
            qdmRenderPhotoPreviews(qdmSelectedPhotos);
            qdmUpdatePhotoStatus();
        });

        article.append(image, label, removeButton);
        qdmPhotoPreviews.append(article);
    });
}

/**
 * Rôle : Distinguer visuellement les photographies conservées de celles dont la suppression est demandée.
 * Paramètres : Aucun.
 * Retour : Aucun.
 */
function qdmUpdateExistingPhotoAppearance() {
    qdmExistingPhotoRemovalInputs.forEach(function updateExistingPhoto(input) {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const photoPreview = input.closest('[data-existing-photo]');

        if (photoPreview instanceof HTMLElement) {
            photoPreview.classList.toggle('photo-preview--removed', input.checked);
        }
    });
}

/**
 * Rôle : Réagir à une sélection et refuser localement les fichiers manifestement invalides.
 * Paramètres : Événement de changement du champ de fichiers.
 * Retour : Aucun.
 */
function qdmHandlePhotoSelection(event) {
    const newFiles = Array.from(event.target.files);

    const invalidFile = newFiles.find(function findInvalidPhoto(file) {
        return !qdmIsAcceptedPhoto(file);
    });

    if (invalidFile) {
        qdmSynchronizePhotoInput();
        let validationMessage = 'Corrigez les champs signalés avant de publier l’annonce.';

        if (qdmIsEditMode) {
            validationMessage = 'Corrigez les champs signalés avant d’enregistrer les modifications.';
        }

        qdmPhotoStatus.textContent = validationMessage;
        return;
    }

    const combinedPhotos = qdmSelectedPhotos.slice();
    const knownIdentifiers = combinedPhotos.map(function identifyKnownPhoto(file) {
        return qdmPhotoIdentifier(file);
    });

    newFiles.forEach(function addNewPhoto(file) {
        const identifier = qdmPhotoIdentifier(file);

        if (!knownIdentifiers.includes(identifier)) {
            combinedPhotos.push(file);
            knownIdentifiers.push(identifier);
        }
    });

    if (qdmActiveExistingPhotoCount() + combinedPhotos.length > 3) {
        qdmSynchronizePhotoInput();
        qdmPhotoStatus.textContent = 'Capacité atteinte. Supprimez une photo pour en ajouter une autre. La suivante devient principale si la première est supprimée.';
        return;
    }

    qdmSelectedPhotos = combinedPhotos;
    qdmSynchronizePhotoInput();
    qdmRenderPhotoPreviews(qdmSelectedPhotos);
    qdmUpdatePhotoStatus();
}

/**
 * Rôle : Réagir au retrait ou au rétablissement d'une photographie existante.
 * Paramètres : Événement de changement de la case de retrait.
 * Retour : Aucun.
 */
function qdmHandleExistingPhotoChange(event) {
    if (qdmActiveExistingPhotoCount() + qdmSelectedPhotos.length > 3
        && event.target instanceof HTMLInputElement
    ) {
        event.target.checked = true;
        qdmPhotoStatus.textContent = 'Capacité atteinte. Supprimez une photo pour en ajouter une autre. La suivante devient principale si la première est supprimée.';
        return;
    }

    qdmUpdateExistingPhotoAppearance();
    qdmRenderPhotoPreviews(qdmSelectedPhotos);
    qdmUpdatePhotoStatus();
}

/**
 * Rôle : Confirmer visuellement la prise en compte du formulaire avant son envoi au serveur.
 * Paramètres : Événement de soumission du formulaire d'annonce.
 * Retour : Aucun.
 */
function qdmHandleListingFormSubmission(event) {
    const totalPhotoCount = qdmActiveExistingPhotoCount() + qdmSelectedPhotos.length;

    if (totalPhotoCount > 3) {
        event.preventDefault();
        qdmPhotoStatus.textContent = 'Capacité atteinte. Supprimez une photo pour en ajouter une autre. La suivante devient principale si la première est supprimée.';
        return;
    }

    if (event.submitter instanceof HTMLButtonElement) {
        event.submitter.disabled = true;
        event.submitter.textContent = 'Enregistrement en cours…';
    }
}

if (qdmPhotoInput && qdmPhotoPreviews && qdmPhotoStatus) {
    qdmPhotoInput.addEventListener('change', qdmHandlePhotoSelection);
    qdmExistingPhotoRemovalInputs.forEach(function observeExistingPhoto(input) {
        input.addEventListener('change', qdmHandleExistingPhotoChange);
    });
    qdmUpdateExistingPhotoAppearance();
    qdmUpdatePhotoStatus();
}

if (qdmListingForm) {
    qdmListingForm.addEventListener('submit', qdmHandleListingFormSubmission);
}

