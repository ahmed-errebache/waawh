<?php
session_start();
require_once __DIR__ . '/config.php';
require_login('admin');

$db = connect_db();

// Determine survey ID to which this question belongs
$survey_id = $_GET['survey_id'] ?? ($_POST['survey_id'] ?? null);
if (!$survey_id) {
    echo 'Paramètre survey_id manquant.';
    exit;
}
// Verify that survey exists
$stmt = $db->prepare('SELECT * FROM surveys WHERE id = ?');
$stmt->execute([$survey_id]);
$survey = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$survey) {
    echo 'Sondage introuvable.';
    exit;
}

// Determine if editing an existing question
$question = null;
if (isset($_GET['id'])) {
    $stmt = $db->prepare('SELECT * FROM questions WHERE id = ? AND survey_id = ?');
    $stmt->execute([$_GET['id'], $survey_id]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$question) {
        echo 'Question introuvable.';
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = $_POST['id'] ?? null;
    $qtype = $_POST['qtype'] ?? 'quiz';
    // Statement text and media toggles
    $has_text = isset($_POST['has_text']) ? true : false;
    $qtext = $has_text ? trim($_POST['qtext'] ?? '') : null;
    // Upload statement media
    $qmedia_path = $question['qmedia'] ?? null;
    if (isset($_POST['has_media']) && !empty($_FILES['qmedia']['tmp_name'])) {
        $file = $_FILES['qmedia'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png','image/jpeg','image/gif','image/webp','video/mp4','audio/mpeg','application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (in_array($mime, $allowed) && $file['size'] <= 50 * 1024 * 1024) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = bin2hex(random_bytes(8)) . '.' . $ext;
                $destination = __DIR__ . '/uploads/' . $newName;
                move_uploaded_file($file['tmp_name'], $destination);
                $qmedia_path = 'uploads/' . $newName;
            } else {
                $error = 'Fichier media de l\'énoncé invalide ou trop volumineux.';
            }
        }
    }
    // Question timing and points
    $seconds = isset($_POST['has_timer']) ? (int)($_POST['seconds'] ?? 0) : null;
    $points = isset($_POST['has_points']) ? (int)($_POST['points'] ?? 0) : null;
    $confirm_text = trim($_POST['confirm_text'] ?? '');
    $wrong_text = trim($_POST['wrong_text'] ?? '');
    // Explanation text and media
    $explain_text = isset($_POST['has_explain_text']) ? trim($_POST['explain_text'] ?? '') : null;
    $explain_media = $question['explain_media'] ?? null;
    if (isset($_POST['has_explain_media']) && !empty($_FILES['explain_media']['tmp_name'])) {
        $file = $_FILES['explain_media'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/png','image/jpeg','image/gif','image/webp','video/mp4','audio/mpeg','application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (in_array($mime, $allowed) && $file['size'] <= 50 * 1024 * 1024) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = bin2hex(random_bytes(8)) . '.' . $ext;
                $destination = __DIR__ . '/uploads/' . $newName;
                move_uploaded_file($file['tmp_name'], $destination);
                $explain_media = 'uploads/' . $newName;
            } else {
                $error = 'Fichier media de l\'explication invalide ou trop volumineux.';
            }
        }
    }
    // Prepare choices and correct answers depending on type
    $choices = [];
    $correct_indices = [];
    switch ($qtype) {
        case 'quiz':
            // Each line in options
            $options = array_filter(array_map('trim', explode("\n", $_POST['options'] ?? '')));
            $choices = $options;
            // correct indices string like "0,2"
            $selected = trim($_POST['correct_indices'] ?? '');
            if ($selected !== '') {
                $correct_indices = array_map('intval', explode(',', $selected));
            }
            break;
        case 'truefalse':
            $choices = ['Vrai','Faux'];
            $correct = $_POST['truefalse_correct'] ?? 'true';
            $correct_indices = [($correct === 'true') ? 0 : 1];
            break;
        case 'opinion':
            // Questions d'avis : options sans bonne réponse
            $options = array_filter(array_map('trim', explode("\n", $_POST['options'] ?? '')));
            $choices = $options;
            $correct_indices = [];
            break;
        case 'feedback':
            // Type remerciement/commentaire libre : pas de choix ni de correction
            $choices = [];
            $correct_indices = [];
            break;
        case 'short':
            // Acceptable answers lines
            $answers = array_filter(array_map('trim', explode("\n", $_POST['short_answers'] ?? '')));
            $choices = $answers;
            // For short answers we treat any as correct; store in choices; correct_indices not used
            $correct_indices = [];
            break;
        case 'long':
            // No scoring
            $choices = [];
            $correct_indices = [];
            break;
        case 'rating':
            $max = (int)($_POST['rating_max'] ?? 5);
            $choices = [$max];
            break;
        case 'date':
            $dates = array_filter(array_map('trim', explode("\n", $_POST['date_answers'] ?? '')));
            $choices = $dates;
            break;
        default:
            break;
    }
    if (!$error) {
        $choice_json = json_encode($choices);
        $correct_json = json_encode($correct_indices);
        if ($id) {
            // Update existing
            $db->prepare('UPDATE questions SET qtype=?, qtext=?, qmedia=?, choices=?, correct_indices=?, confirm_text=?, wrong_text=?, explain_text=?, explain_media=?, seconds=?, points=? WHERE id=? AND survey_id=?')
               ->execute([$qtype, $qtext, $qmedia_path, $choice_json, $correct_json, $confirm_text, $wrong_text, $explain_text, $explain_media, $seconds, $points, $id, $survey_id]);
        } else {
            $db->prepare('INSERT INTO questions (survey_id, qtype, qtext, qmedia, choices, correct_indices, confirm_text, wrong_text, explain_text, explain_media, seconds, points) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$survey_id, $qtype, $qtext, $qmedia_path, $choice_json, $correct_json, $confirm_text, $wrong_text, $explain_text, $explain_media, $seconds, $points]);
        }
        header('Location: builder.php?id=' . $survey_id);
        exit;
    }
}

// For editing, decode JSONs
$choices_decoded = [];
$correct_decoded = [];
if ($question) {
    $choices_decoded = $question['choices'] ? json_decode($question['choices'], true) : [];
    $correct_decoded = $question['correct_indices'] ? json_decode($question['correct_indices'], true) : [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $question ? 'Modifier la question' : 'Nouvelle question' ?> – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #FFF9F2; }
    </style>
</head>
<body>
    <div class="container py-4">
        <h2><?= $question ? 'Modifier la question' : 'Créer une nouvelle question' ?></h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= esc($error) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" id="questionForm">
            <?= csrf_token() ?>
            <input type="hidden" name="survey_id" value="<?= $survey_id ?>">
            <?php if ($question): ?>
                <input type="hidden" name="id" value="<?= $question['id'] ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Type de question</label>
                <select name="qtype" id="qtype" class="form-select" required>
                    <?php
                    $types = ['quiz'=>'Choix multiples','truefalse'=>'Vrai/Faux','opinion'=>'Avis (choix sans bonne réponse)','feedback'=>'Remerciement (commentaire libre)','short'=>'Réponse courte','long'=>'Réponse longue','rating'=>'Notation','date'=>'Date'];
                    foreach ($types as $value => $label):
                        $selected = ($question && $question['qtype'] === $value) ? 'selected' : '';
                        echo "<option value='$value' $selected>$label</option>";
                    endforeach;
                    ?>
                </select>
            </div>
            <!-- Statement text and media -->
            <div class="mb-2">
                <label class="form-label">Énoncé</label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="has_text" name="has_text" <?= ($question && $question['qtext']) ? 'checked' : '' ?> >
                    <label class="form-check-label" for="has_text">Inclure du texte</label>
                </div>
                <textarea name="qtext" id="qtext" class="form-control mt-2" rows="3" style="display: none;"><?= esc($question['qtext'] ?? '') ?></textarea>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="has_media" name="has_media" <?= ($question && $question['qmedia']) ? 'checked' : '' ?> >
                    <label class="form-check-label" for="has_media">Inclure un média (image/vidéo/audio/PDF)</label>
                </div>
                <input type="file" name="qmedia" id="qmedia" class="form-control mt-2" style="display: none;">
                <?php if ($question && $question['qmedia']): ?>
                    <div class="mt-2">
                        <small>Média existant: <?= esc($question['qmedia']) ?></small>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Timing and points -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="has_timer" name="has_timer" <?= ($question && $question['seconds']) ? 'checked' : '' ?> >
                        <label class="form-check-label" for="has_timer">Question chronométrée</label>
                    </div>
                    <input type="number" min="1" max="600" name="seconds" id="seconds" class="form-control mt-2" placeholder="Nombre de secondes" value="<?= esc($question['seconds'] ?? '') ?>" style="display: none;">
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="has_points" name="has_points" <?= ($question && $question['points']) ? 'checked' : '' ?> >
                        <label class="form-check-label" for="has_points">Question notée</label>
                    </div>
                    <input type="number" min="0" max="1000" name="points" id="points" class="form-control mt-2" placeholder="Points" value="<?= esc($question['points'] ?? '') ?>" style="display: none;">
                </div>
            </div>
            <!-- Type-specific fields -->
            <div id="typeFields"></div>
            <!-- Feedback messages -->
            <div class="mb-3">
                <label class="form-label">Texte si la réponse est juste</label>
                <textarea name="confirm_text" class="form-control" rows="2"><?= esc($question['confirm_text'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Texte si la réponse est fausse</label>
                <textarea name="wrong_text" class="form-control" rows="2"><?= esc($question['wrong_text'] ?? '') ?></textarea>
            </div>
            <!-- Explanation section -->
            <div class="mb-3">
                <label class="form-label">Explication / Feedback</label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="has_explain_text" name="has_explain_text" <?= ($question && $question['explain_text']) ? 'checked' : '' ?> >
                    <label class="form-check-label" for="has_explain_text">Inclure du texte</label>
                </div>
                <textarea name="explain_text" id="explain_text" class="form-control mt-2" rows="3" style="display:none;"><?= esc($question['explain_text'] ?? '') ?></textarea>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="has_explain_media" name="has_explain_media" <?= ($question && $question['explain_media']) ? 'checked' : '' ?> >
                    <label class="form-check-label" for="has_explain_media">Inclure un média (image/vidéo/audio/PDF)</label>
                </div>
                <input type="file" name="explain_media" id="explain_media" class="form-control mt-2" style="display:none;">
                <?php if ($question && $question['explain_media']): ?>
                    <div class="mt-2"><small>Média existant: <?= esc($question['explain_media']) ?></small></div>
                <?php endif; ?>
            </div>
            <div class="d-flex">
                <button type="submit" class="btn btn-success me-2">Enregistrer</button>
                <a href="builder.php?id=<?= $survey_id ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
    <script>
    // Function to toggle display of elements
    function toggleField(triggerId, fieldId) {
        document.getElementById(fieldId).style.display = document.getElementById(triggerId).checked ? 'block' : 'none';
    }
    document.getElementById('has_text').addEventListener('change', () => {
        toggleField('has_text', 'qtext');
    });
    document.getElementById('has_media').addEventListener('change', () => {
        toggleField('has_media', 'qmedia');
    });
    document.getElementById('has_timer').addEventListener('change', () => {
        toggleField('has_timer', 'seconds');
    });
    document.getElementById('has_points').addEventListener('change', () => {
        toggleField('has_points', 'points');
    });
    document.getElementById('has_explain_text').addEventListener('change', () => {
        toggleField('has_explain_text', 'explain_text');
    });
    document.getElementById('has_explain_media').addEventListener('change', () => {
        toggleField('has_explain_media', 'explain_media');
    });
    // Show/hide statement fields on load
    toggleField('has_text', 'qtext');
    toggleField('has_media', 'qmedia');
    toggleField('has_timer', 'seconds');
    toggleField('has_points', 'points');
    toggleField('has_explain_text', 'explain_text');
    toggleField('has_explain_media', 'explain_media');
    // Function to render type-specific inputs
    function renderTypeFields() {
        const container = document.getElementById('typeFields');
        const type = document.getElementById('qtype').value;
        container.innerHTML = '';
        if (type === 'quiz') {
            // Options input and correct indices
            const opts = `<?php echo str_replace("`", "\\`", esc(join("\n", $choices_decoded ?? []))); ?>`;
            const correct = `<?php echo str_replace("`", "\\`", esc(join(',', $correct_decoded ?? []))); ?>`;
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Options (une par ligne)</label>
                    <textarea name="options" class="form-control" rows="4">${opts}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Index des bonnes réponses (séparés par des virgules, base 0)</label>
                    <input type="text" name="correct_indices" class="form-control" value="${correct}">
                </div>
            `;
        } else if (type === 'truefalse') {
            // True false choose correct
            const selected = `<?php if ($question && $question['qtype']=='truefalse') { echo ($correct_decoded[0] ?? 0) === 0 ? 'true' : 'false'; } ?>`;
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Bonne réponse</label>
                    <select name="truefalse_correct" class="form-select">
                        <option value="true" ${selected === 'true' ? 'selected' : ''}>Vrai</option>
                        <option value="false" ${selected === 'false' ? 'selected' : ''}>Faux</option>
                    </select>
                </div>
            `;
        } else if (type === 'short') {
            const answers = `<?php echo str_replace("`", "\\`", esc(join("\n", $choices_decoded ?? []))); ?>`;
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Réponses acceptées (une par ligne)</label>
                    <textarea name="short_answers" class="form-control" rows="4">${answers}</textarea>
                </div>
            `;
        } else if (type === 'rating') {
            const maxRating = `<?php if ($question && $question['qtype']=='rating') { echo esc($choices_decoded[0] ?? 5); } ?>`;
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Note maximale (nombre d'étoiles)</label>
                    <input type="number" name="rating_max" class="form-control" min="2" max="10" value="${maxRating || 5}">
                </div>
            `;
        } else if (type === 'date') {
            const dates = `<?php echo str_replace("`", "\\`", esc(join("\n", $choices_decoded ?? []))); ?>`;
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Dates acceptées (AAAA-MM-JJ, une par ligne)</label>
                    <textarea name="date_answers" class="form-control" rows="3">${dates}</textarea>
                </div>
            `;
        } else if (type === 'long') {
            // No additional fields
        } else if (type === 'opinion') {
            // Avis : seulement les options sans indices corrects
            const optsOpinion = `<?php echo str_replace("`", "\\`", esc(join("\n", $choices_decoded ?? []))); ?>`;
            container.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Options (une par ligne)</label>
                    <textarea name="options" class="form-control" rows="4">${optsOpinion}</textarea>
                </div>
            `;
        } else if (type === 'feedback') {
            // Remerciement : pas de champs supplémentaires
        }
    }
    document.getElementById('qtype').addEventListener('change', renderTypeFields);
    // Render fields on load
    renderTypeFields();
    </script>
</body>
</html>