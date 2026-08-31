/**
 * Description générale : Gestion locale des photographies du formulaire d'annonce.
 * Rôle : Prévisualiser les fichiers choisis et expliquer leur ordre avant l'envoi classique.
 * Tâches : Contrôler ergonomiquement le nombre, le type et la taille puis créer des aperçus sûrs.
 * Liens avec les autres fichiers : Est chargé par listing-form.php sans appel AJAX.
 */

const qdmPhotoInput = document.querySelector('[data-photo-input]');
const qdmPhotoPreviews = document.querySelector('[data-photo-previews]');
const qdmPhotoStatus = document.querySelector('[data-photo-status]');

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
        article.className = 'photo-preview';
        image.src = URL.createObjectURL(file);
        image.alt = 'Aperçu de ' + file.name;
        label.textContent = 'Image ' + (index + 1);

        if (index === 0) {
            label.textContent += ' — principale';
        }

        article.append(image, label);
        qdmPhotoPreviews.append(article);
    });
}

/**
 * Rôle : Réagir à une sélection et refuser localement les fichiers manifestement invalides.
 * Paramètres : Événement de changement du champ de fichiers.
 * Retour : Aucun.
 */
function qdmHandlePhotoSelection(event) {
    const files = Array.from(event.target.files);

    if (files.length > 3) {
        event.target.value = '';
        qdmClearPhotoPreviews();
        qdmPhotoStatus.textContent = 'Trois photographies sont autorisées au maximum.';
        return;
    }

    const invalidFile = files.find(function findInvalidPhoto(file) {
        return !qdmIsAcceptedPhoto(file);
    });

    if (invalidFile) {
        event.target.value = '';
        qdmClearPhotoPreviews();
        qdmPhotoStatus.textContent = 'Utilisez des images JPEG, PNG ou WebP de 5 Mo maximum.';
        return;
    }

    qdmRenderPhotoPreviews(files);
    qdmPhotoStatus.textContent = files.length + ' photographie(s) prête(s) à être envoyée(s).';
}

if (qdmPhotoInput && qdmPhotoPreviews && qdmPhotoStatus) {
    qdmPhotoInput.addEventListener('change', qdmHandlePhotoSelection);
}

