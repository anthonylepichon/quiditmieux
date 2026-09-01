/**
 * Description générale : Gestion locale des photographies du formulaire d'annonce.
 * Rôle : Cumuler et prévisualiser les fichiers choisis avant l'envoi classique.
 * Tâches : Conserver les sélections successives, contrôler leur nombre, leur type et leur taille puis permettre leur retrait.
 * Liens avec les autres fichiers : Est chargé par listing-form.php sans appel AJAX.
 */

const qdmPhotoInput = document.querySelector('[data-photo-input]');
const qdmPhotoPreviews = document.querySelector('[data-photo-previews]');
const qdmPhotoStatus = document.querySelector('[data-photo-status]');
let qdmSelectedPhotos = [];

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
    if (qdmSelectedPhotos.length === 0) {
        qdmPhotoStatus.textContent = 'Aucune nouvelle photographie sélectionnée.';
        return;
    }

    qdmPhotoStatus.textContent = qdmSelectedPhotos.length
        + ' photographie(s) prête(s) à être envoyée(s).';
}

/**
 * Rôle : Construire les aperçus locaux dans l'ordre réel d'envoi.
 * Paramètres : Liste de fichiers validés localement.
 * Retour : Aucun.
 */
function qdmRenderPhotoPreviews(files) {
    qdmClearPhotoPreviews();

    files.forEach(function renderPhoto(file, index) {
        const article = document.createElement('article');
        const image = document.createElement('img');
        const label = document.createElement('span');
        const removeButton = document.createElement('button');
        article.className = 'photo-preview';
        image.src = URL.createObjectURL(file);
        image.alt = 'Aperçu de ' + file.name;
        label.textContent = 'Image ' + (index + 1);
        removeButton.className = 'button button--secondary button--compact';
        removeButton.type = 'button';
        removeButton.textContent = 'Retirer';
        removeButton.setAttribute('aria-label', 'Retirer ' + file.name);

        if (index === 0) {
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
        qdmPhotoStatus.textContent = 'Utilisez des images JPEG, PNG ou WebP de 5 Mo maximum.';
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

    if (combinedPhotos.length > 3) {
        qdmSynchronizePhotoInput();
        qdmPhotoStatus.textContent = 'Trois photographies sont autorisées au maximum. Les images déjà choisies sont conservées.';
        return;
    }

    qdmSelectedPhotos = combinedPhotos;
    qdmSynchronizePhotoInput();
    qdmRenderPhotoPreviews(qdmSelectedPhotos);
    qdmUpdatePhotoStatus();
}

if (qdmPhotoInput && qdmPhotoPreviews && qdmPhotoStatus) {
    qdmPhotoInput.addEventListener('change', qdmHandlePhotoSelection);
}

