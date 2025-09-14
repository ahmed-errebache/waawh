<?php
session_start();
require_once __DIR__ . '/config.php';

// If leaving session (logout for participant)
if (isset($_GET['leave'])) {
    unset($_SESSION['participant_session_id'], $_SESSION['participant_name']);
    header('Location: join.php');
    exit;
}

$db = connect_db();
$error = '';

// Handle join form submission
if (isset($_POST['join'])) {
    $pin = trim($_POST['pin'] ?? '');
    $name = trim($_POST['name'] ?? '');
    if ($pin === '' || $name === '') {
        $error = 'Veuillez saisir un PIN et un nom.';
    } else {
        // Find active session with this PIN
        $stmt = $db->prepare('SELECT * FROM sessions WHERE pin = ? AND is_active = 1');
        $stmt->execute([$pin]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            $error = 'Session non trouvée ou terminée.';
        } else {
            // Insert or ignore participant
            $db->prepare('INSERT OR IGNORE INTO participants (session_id, name) VALUES (?, ?)')
               ->execute([$session['id'], $name]);
            $_SESSION['participant_session_id'] = $session['id'];
            $_SESSION['participant_name'] = $name;
            header('Location: join.php');
            exit;
        }
    }
}

// If participant already joined
$session_id = $_SESSION['participant_session_id'] ?? null;
$participant_name = $_SESSION['participant_name'] ?? null;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejoindre une session – WAAWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #FFF9F2; }
        /* Simple fade-in animation for question content */
        .fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <?php if (!$session_id || !$participant_name): ?>
            <h2 class="mb-4">Rejoindre une session</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= esc($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <?= csrf_token() ?>
                <input type="hidden" name="join" value="1">
                <div class="mb-3">
                    <label class="form-label">PIN</label>
                    <input type="text" name="pin" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Votre nom</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Rejoindre</button>
            </form>
        <?php else: ?>
            <?php
            // Participant view
            // Retrieve session
            $stmt = $db->prepare('SELECT * FROM sessions WHERE id = ?');
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$session || !$session['is_active']) {
                echo '<div class="alert alert-info">Cette session est terminée.</div>';
                echo '<a href="join.php?leave=1" class="btn btn-secondary">Quitter</a>';
            } else {
                // Get survey theme (colours) for basic styling
                $stmt = $db->prepare('SELECT theme_json FROM surveys WHERE id = ?');
                $stmt->execute([$session['survey_id']]);
                $themeData = $stmt->fetchColumn();
                $theme = ['primary' => '#FFBF69','accent' => '#2EC4B6','background' => '#FFF9F2','background_image' => null];
                if ($themeData) {
                    $dec = json_decode($themeData, true);
                    if (is_array($dec)) { $theme = array_merge($theme, $dec); }
                }
                // Apply either a background image or a background colour
                if (!empty($theme['background_image'])) {
                    echo '<style>body { background-image: url(' . esc($theme['background_image']) . '); background-size: cover; background-position: center; }</style>';
                } else {
                    echo '<style>body { background-color:' . esc($theme['background']) . '; }</style>';
                }
                // Fetch all questions ordered by ID
                $stmt = $db->prepare('SELECT * FROM questions WHERE survey_id = ? ORDER BY id');
                $stmt->execute([$session['survey_id']]);
                $allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $currentIndex = (int)$session['current_question_index'];
                if ($currentIndex >= count($allQuestions)) {
                    echo '<h3 class="mb-3">La session est terminée. Merci de votre participation !</h3>';
                    echo '<a href="join.php?leave=1" class="btn btn-secondary">Quitter</a>';
                } else {
                    $question = $allQuestions[$currentIndex];
                    // Check if participant already answered this question
                    $stmt = $db->prepare('SELECT * FROM responses WHERE session_id=? AND question_id=? AND user_name=?');
                    $stmt->execute([$session_id, $question['id'], $participant_name]);
                    $existingResponse = $stmt->fetch(PDO::FETCH_ASSOC);
                    $reveal = (int)$session['reveal_state'];
                    echo '<div class="fade-in">';
                    echo '<h3 class="mb-3">Question ' . ($currentIndex + 1) . '</h3>';
                    // Display statement text if exists
                    if ($question['qtext']) {
                        echo '<p>' . nl2br(esc($question['qtext'])) . '</p>';
                    }
                    // Display statement media
                    if ($question['qmedia']) {
                        $path = esc($question['qmedia']);
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) {
                            echo '<img src="' . $path . '" alt="media" class="img-fluid mb-3"  style="max-width:100%;height:auto;">';
                        } elseif ($ext === 'mp4') {
                            echo '<video controls class="mb-3" style="max-width:100%"><source src="' . $path . '" type="video/mp4"></video>';
                        } elseif ($ext === 'mp3') {
                            echo '<audio controls class="mb-3"><source src="' . $path . '" type="audio/mpeg"></audio>';
                        } elseif ($ext === 'pdf') {
                            echo '<embed src="' . $path . '" type="application/pdf" class="mb-3" style="width:100%; height:400px;"></embed>';
                        }
                    }
                    // If not revealed yet
                    if ($reveal === 0) {
                        // Determine if we should allow changing answers for this question type
                        $allowChanges = in_array($question['qtype'], ['quiz','truefalse']);
                        // Decode the participant's existing answer indices if any
                        $prevIndices = [];
                        if ($existingResponse && $existingResponse['answer_indices'] !== null && $existingResponse['answer_indices'] !== '') {
                            $tmp = json_decode($existingResponse['answer_indices'], true);
                            if (is_array($tmp)) {
                                $prevIndices = $tmp;
                            } elseif ($tmp !== null) {
                                $prevIndices = [$tmp];
                            }
                        }
                        if ($existingResponse && !$allowChanges) {
                            // Participant already answered a non-changeable question
                            echo '<div class="alert alert-info">Vous avez répondu. En attente de la correction...</div>';
                        }
                        // If type is quiz, truefalse or opinion, display choices outside of a form so no submission occurs
                        if (in_array($question['qtype'], ['quiz','truefalse','opinion'])) {
                            echo '<div id="choice-container">';
                            $choices = $question['choices'] ? json_decode($question['choices'], true) : [];
                            $corrects = $question['correct_indices'] ? json_decode($question['correct_indices'], true) : [];
                            if ($question['qtype'] === 'quiz' || $question['qtype'] === 'opinion') {
                                $multiple = count($corrects) > 1;
                                foreach ($choices as $idx => $opt) {
                                    $inputType = $multiple ? 'checkbox' : 'radio';
                                    $name = $multiple ? 'answer_indices[]' : 'answer_index';
                                    $checked = in_array($idx, $prevIndices) ? ' checked' : '';
                                    echo '<div class="form-check">';
                                    echo '<input class="form-check-input" type="' . $inputType . '" name="' . $name . '" value="' . $idx . '" id="opt_' . $idx . '"' . $checked . '>';
                                    echo '<label class="form-check-label" for="opt_' . $idx . '">' . esc($opt) . '</label>';
                                    echo ' <small class="text-muted ms-2 answer-count" data-idx="' . $idx . '">0</small>';
                                    echo '</div>';
                                }
                            } elseif ($question['qtype'] === 'truefalse') {
                                $checkedTrue = in_array(0, $prevIndices) ? ' checked' : '';
                                $checkedFalse = in_array(1, $prevIndices) ? ' checked' : '';
                                // True option
                                echo '<div class="form-check">';
                                echo '<input class="form-check-input" type="radio" name="answer_index" value="0" id="true"' . $checkedTrue . '>';
                                echo '<label class="form-check-label" for="true">Vrai</label>';
                                echo ' <small class="text-muted ms-2 answer-count" data-idx="0">0</small>';
                                echo '</div>';
                                // False option
                                echo '<div class="form-check">';
                                echo '<input class="form-check-input" type="radio" name="answer_index" value="1" id="false"' . $checkedFalse . '>';
                                echo '<label class="form-check-label" for="false">Faux</label>';
                                echo ' <small class="text-muted ms-2 answer-count" data-idx="1">0</small>';
                                echo '</div>';
                            }
                            echo '</div>';
                            // Ajouter un bouton Envoyer pour valider explicitement la réponse sélectionnée sans empêcher les modifications ultérieures
                            echo '<button type="button" id="submitChoiceBtn" class="btn btn-success mt-2">Envoyer</button>';
                            // Button to modify the answer after it has been sent
                            echo '<button type="button" id="modifyChoiceBtn" class="btn btn-warning mt-2 d-none">Modifier</button>';
                            echo '<div id="submitChoiceMsg" class="text-success mt-2 d-none">Réponse enregistrée.</div>';
                        } elseif (!$existingResponse || $allowChanges) {
                            // For other question types, use a form with submission
                            echo '<form method="post">';
                            echo csrf_token();
                            echo '<input type="hidden" name="answer" value="1">';
                            echo '<input type="hidden" name="question_id" value="' . $question['id'] . '">';
                            echo '<input type="hidden" name="session_id" value="' . $session_id . '">';
                            echo '<input type="hidden" name="qtype" value="' . esc($question['qtype']) . '">';
                            // Determine choices
                            $choices = $question['choices'] ? json_decode($question['choices'], true) : [];
                            switch ($question['qtype']) {
                                case 'short':
                                    echo '<div class="mb-3">';
                                    $val = $existingResponse && $existingResponse['answer_indices'] !== null ? esc(json_decode($existingResponse['answer_indices'], true) ?: '') : '';
                                    echo '<input type="text" name="answer_text" class="form-control" placeholder="Votre réponse" value="' . $val . '" required>';
                                    echo '</div>';
                                    break;
                                case 'long':
                                    echo '<div class="mb-3">';
                                    $val = $existingResponse && $existingResponse['answer_indices'] !== null ? esc(json_decode($existingResponse['answer_indices'], true) ?: '') : '';
                                    echo '<textarea name="answer_long" class="form-control" rows="4" placeholder="Votre réponse">' . $val . '</textarea>';
                                    echo '</div>';
                                    break;
                                case 'rating':
                                    $max = $choices[0] ?? 5;
                                    $val = $existingResponse && $existingResponse['answer_indices'] !== null ? (int)(json_decode($existingResponse['answer_indices'], true) ?: '') : '';
                                    echo '<div class="mb-3">';
                                    echo '<label for="ratingInput">Votre note (1 à ' . $max . ')</label>';
                                    echo '<input type="number" name="answer_rating" id="ratingInput" min="1" max="' . $max . '" class="form-control" value="' . $val . '" required>';
                                    echo '</div>';
                                    break;
                                case 'date':
                                    $val = $existingResponse && $existingResponse['answer_indices'] !== null ? esc(json_decode($existingResponse['answer_indices'], true) ?: '') : '';
                                    echo '<div class="mb-3">';
                                    echo '<input type="date" name="answer_date" class="form-control" value="' . $val . '" required>';
                                    echo '</div>';
                                    break;
                                case 'feedback':
                                    // Question de type remerciement / avis libre: champ commentaire libre
                                    $val = '';
                                    if ($existingResponse && $existingResponse['answer_indices'] !== null) {
                                        $decoded = json_decode($existingResponse['answer_indices'], true);
                                        if (!is_array($decoded) && $decoded !== null) {
                                            $val = esc($decoded);
                                        }
                                    }
                                    echo '<div class="mb-3">';
                                    echo '<textarea name="answer_feedback" class="form-control" rows="3" placeholder="Votre avis">' . $val . '</textarea>';
                                    echo '</div>';
                                    break;
                            }
                            // Always show submit button for these types
                            echo '<button type="submit" class="btn btn-success">Envoyer</button>';
                            echo '</form>';
                        }
                    } else {
                        // Reveal: show message depending on correctness
                        if ($existingResponse) {
                            $is_correct = (int)$existingResponse['is_correct'];
                            if ($is_correct) {
                                echo '<div class="alert alert-success">' . esc($question['confirm_text']) . '</div>';
                            } else {
                                echo '<div class="alert alert-danger">' . esc($question['wrong_text'] ?: 'Mauvaise réponse.') . '</div>';
                            }
                        } else {
                            echo '<div class="alert alert-info">Vous n\'avez pas répondu à cette question.</div>';
                        }
                        // Display a detailed answer view for certain types
                        $choices = $question['choices'] ? json_decode($question['choices'], true) : [];
                        $corrects = $question['correct_indices'] ? json_decode($question['correct_indices'], true) : [];
                        $userAns = [];
                        if ($existingResponse && $existingResponse['answer_indices'] !== null) {
                            $decoded = json_decode($existingResponse['answer_indices'], true);
                            // ensure arrays for uniformity
                            if (is_array($decoded)) {
                                $userAns = $decoded;
                            } else if ($decoded !== null) {
                                $userAns = [$decoded];
                            }
                        }
                        if (in_array($question['qtype'], ['quiz','truefalse','opinion'])) {
                            echo '<ul class="list-group mb-3">';
                            foreach ($choices as $idx => $opt) {
                                $isCorrect = in_array($idx, $corrects);
                                $selected = in_array($idx, $userAns);
                                $status = '';
                                $class = 'list-group-item d-flex justify-content-between align-items-center';
                                if ($question['qtype'] === 'opinion') {
                                    // Pour les questions d'opinion, on n'affiche aucun indicateur de bonne/mauvaise réponse
                                    $status = '';
                                } else {
                                    if ($isCorrect && $selected) {
                                        $status = '<span class="badge bg-success">✅</span>';
                                        $class .= ' list-group-item-success';
                                    } elseif ($isCorrect) {
                                        $status = '<span class="badge bg-success">✔️</span>';
                                        $class .= ' list-group-item-success';
                                    } elseif ($selected) {
                                        $status = '<span class="badge bg-danger">❌</span>';
                                        $class .= ' list-group-item-danger';
                                    }
                                }
                                echo '<li class="' . $class . '">' . esc($opt) . ' ' . $status . '</li>';
                            }
                            echo '</ul>';
                        } elseif ($question['qtype'] === 'short') {
                            // Show accepted answers
                            if (!empty($choices)) {
                                echo '<p><strong>Réponses acceptées :</strong> ' . esc(implode(', ', $choices)) . '</p>';
                            }
                        } elseif ($question['qtype'] === 'date') {
                            if (!empty($choices)) {
                                echo '<p><strong>Dates correctes :</strong> ' . esc(implode(', ', $choices)) . '</p>';
                            }
                        } elseif ($question['qtype'] === 'feedback') {
                            // Afficher le texte soumis pour les questions de remerciement
                            if ($existingResponse && $existingResponse['answer_indices'] !== null) {
                                $ansText = json_decode($existingResponse['answer_indices'], true);
                                if (!is_array($ansText) && $ansText !== null) {
                                    echo '<p><strong>Votre avis :</strong> ' . esc($ansText) . '</p>';
                                }
                            }
                        }
                        // Show explanation text and media
                        if ($question['explain_text']) {
                            echo '<p>' . nl2br(esc($question['explain_text'])) . '</p>';
                        }
                        if ($question['explain_media']) {
                            $path = esc($question['explain_media']);
                            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                            if (in_array($ext, ['png','jpg','jpeg','gif','webp'])) {
                                echo '<img src="' . $path . '" alt="explication" class="img-fluid mb-3"   style="max-width:100%;height:auto;">';
                            } elseif ($ext === 'mp4') {
                                echo '<video controls class="mb-3" style="max-width:100%"><source src="' . $path . '" type="video/mp4"></video>';
                            } elseif ($ext === 'mp3') {
                                echo '<audio controls class="mb-3"><source src="' . $path . '" type="audio/mpeg"></audio>';
                            } elseif ($ext === 'pdf') {
                                echo '<embed src="' . $path . '" type="application/pdf" style="width:100%;height:400px;" class="mb-3"></embed>';
                            }
                        }
                        echo '<div class="alert alert-secondary">En attente de la question suivante...</div>';
                    }
                    echo '<a href="join.php?leave=1" class="btn btn-link mt-3">Quitter</a>';
                    echo '</div>'; // end fade-in
                }
            }
            ?>
        <?php endif; ?>
    </div>
    <?php
    // Handle answer submission after HTML so we can still output in the same request
    if (isset($_POST['answer']) && $session && !$session['reveal_state']) {
        // Retrieve question again
        $question_id = (int)$_POST['question_id'];
        $stmt = $db->prepare('SELECT * FROM questions WHERE id = ?');
        $stmt->execute([$question_id]);
        $q = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($q) {
            $qtype = $q['qtype'];
            $choices = $q['choices'] ? json_decode($q['choices'], true) : [];
            $correct_indices = $q['correct_indices'] ? json_decode($q['correct_indices'], true) : [];
            $is_correct = 0;
            $payload = [];
            switch ($qtype) {
                case 'quiz':
                    $ans = isset($_POST['answer_indices']) ? $_POST['answer_indices'] : ($_POST['answer_index'] ?? []);
                    $indices = is_array($ans) ? array_map('intval', $ans) : [intval($ans)];
                    sort($indices);
                    $payload = $indices;
                    if ($indices == $correct_indices) {
                        $is_correct = 1;
                    }
                    break;
                case 'truefalse':
                    $ans = (int)($_POST['answer_index'] ?? 0);
                    $payload = [$ans];
                    if ($ans == $correct_indices[0]) {
                        $is_correct = 1;
                    }
                    break;
                case 'short':
                    $ans = trim($_POST['answer_text'] ?? '');
                    $payload = $ans;
                    foreach ($choices as $c) {
                        if (strcasecmp($ans, $c) == 0) {
                            $is_correct = 1;
                            break;
                        }
                    }
                    break;
                case 'long':
                    $ans = trim($_POST['answer_long'] ?? '');
                    $payload = $ans;
                    $is_correct = 0;
                    break;
                case 'rating':
                    $ans = (int)($_POST['answer_rating'] ?? 0);
                    $payload = $ans;
                    $is_correct = 0; // rating questions are not graded
                    break;
                case 'date':
                    $ans = trim($_POST['answer_date'] ?? '');
                    $payload = $ans;
                    foreach ($choices as $c) {
                        if ($ans === $c) {
                            $is_correct = 1;
                            break;
                        }
                    }
                    break;
            }
            // Only insert if not answered yet
            $stmt = $db->prepare('SELECT COUNT(*) FROM responses WHERE session_id=? AND question_id=? AND user_name=?');
            $stmt->execute([$session_id, $question_id, $participant_name]);
            if ($stmt->fetchColumn() == 0) {
                // Insert response
                $db->prepare('INSERT INTO responses (session_id, question_id, user_name, answer_indices, is_correct) VALUES (?,?,?,?,?)')
                   ->execute([$session_id, $question_id, $participant_name, json_encode($payload), $is_correct]);
                // Update participant score if correct and question has points
                if ($is_correct && $q['points']) {
                    $db->prepare('UPDATE participants SET score = score + ? WHERE session_id = ? AND name = ?')
                       ->execute([(int)$q['points'], $session_id, $participant_name]);
                }
            }
            // Redirect to prevent form resubmission
            header('Location: join.php');
            exit;
        }
    }
    ?>
<?php
// Auto-refresh participant view when the host changes question or reveals answer
if (isset($session_id) && $session_id && isset($session) && $session && $session['is_active']) {
    // Determine current question index and reveal state for this page
    $jsIndex = isset($currentIndex) ? (int)$currentIndex : 0;
    $jsReveal = isset($reveal) ? (int)$reveal : 0;
    echo "<script>\n";
    echo "var lastIndex = " . $jsIndex . ";\n";
    echo "var lastReveal = " . $jsReveal . ";\n";
    echo "setInterval(function(){\n";
    echo "  fetch('api/get_session_status.php?session_id=" . $session_id . "')\n";
    echo "    .then(r => r.json())\n";
    echo "    .then(data => {\n";
    echo "      if (data.current_question_index != lastIndex || data.reveal_state != lastReveal) {\n";
    echo "        window.location.reload();\n";
    echo "      }\n";
    echo "    });\n";
    echo "}, 3000);\n";
    echo "</script>\n";
}
?>
<?php
// Ajout d'un script supplémentaire pour mettre à jour les compteurs de réponses en temps réel
if (isset($session_id) && $session_id && isset($session) && $session && $session['is_active']) {
    echo "<script>\n";
    echo "async function fetchAnswerStats() {\n";
    echo "  try {\n";
    echo "    const res = await fetch('api/get_question_stats.php?session_id=" . (int)$session_id . "');\n";
    echo "    if (!res.ok) return;\n";
    echo "    const data = await res.json();\n";
    echo "    const counts = Array.isArray(data.counts) ? data.counts : [];\n";
    echo "    document.querySelectorAll('.answer-count').forEach(function(el) {\n";
    echo "      const idx = parseInt(el.getAttribute('data-idx'), 10);\n";
    echo "      if (!Number.isNaN(idx) && idx >= 0 && idx < counts.length) {\n";
    echo "        el.textContent = counts[idx];\n";
    echo "      }\n";
    echo "    });\n";
    echo "  } catch (e) {\n";
    echo "    console.error('Erreur lors de la récupération des statistiques de réponses:', e);\n";
    echo "  }\n";
    echo "}\n";
    // Exécuter immédiatement et ensuite toutes les 2 secondes
    echo "fetchAnswerStats();\n";
    echo "setInterval(fetchAnswerStats, 2000);\n";

    // Attacher un écouteur de sélection pour les questions à choix afin d'enregistrer la sélection et gérer Envoyer/Modifier
    // Ces variables sont injectées depuis PHP pour identifier la question et le participant
    echo "const currentQType = '" . addslashes($question['qtype']) . "';\n";
    echo "const currentQuestionId = " . (isset($question['id']) ? (int)$question['id'] : 0) . ";\n";
    echo "const participantName = '" . addslashes($participant_name ?? '') . "';\n";
    echo "const sessionIdVal = " . (int)$session_id . ";\n";
    echo "if (currentQType === 'quiz' || currentQType === 'truefalse' || currentQType === 'opinion') {\n";
    // Enregistrer la sélection au fur et à mesure et mettre à jour les statistiques
    echo "  document.querySelectorAll('.form-check-input').forEach(function(input) {\n";
    echo "    input.addEventListener('change', async function() {\n";
    echo "      let selected = [];\n";
    echo "      if (currentQType === 'quiz') {\n";
    echo "        document.querySelectorAll('.form-check-input:checked').forEach(function(el) { const val = parseInt(el.value, 10); if (!Number.isNaN(val)) selected.push(val); });\n";
    echo "      } else {\n";
    echo "        const checked = document.querySelector('.form-check-input:checked');\n";
    echo "        if (checked) { const v = parseInt(checked.value, 10); if (!Number.isNaN(v)) selected.push(v); }\n";
    echo "      }\n";
    echo "      const fd = new FormData();\n";
    echo "      fd.append('session_id', sessionIdVal);\n";
    echo "      fd.append('question_id', currentQuestionId);\n";
    echo "      fd.append('user_name', participantName);\n";
    echo "      selected.forEach(function(idx) { fd.append('answer_indices[]', idx); });\n";
    echo "      try {\n";
    // Utiliser l'API select_answer pour enregistrer les sélections provisoires sans finaliser la réponse
    echo "        await fetch('api/select_answer.php', { method: 'POST', body: fd });\n";
    echo "        fetchAnswerStats();\n";
    echo "      } catch (e) { console.error('Erreur lors de l\'enregistrement de la sélection', e); }\n";
    echo "    });\n";
    echo "  });\n";
    // Ne supprimez pas les écouteurs ici : les sélections doivent être enregistrées en temps réel via select_answer.php
    // Gérer les boutons Envoyer et Modifier
    echo "  const submitBtn = document.getElementById('submitChoiceBtn');\n";
    echo "  const modifyBtn = document.getElementById('modifyChoiceBtn');\n";
    echo "  if (submitBtn) {\n";
    echo "    submitBtn.addEventListener('click', async function() {\n";
    echo "      let selected = [];\n";
    echo "      if (currentQType === 'quiz') {\n";
    echo "        document.querySelectorAll('.form-check-input:checked').forEach(function(el) { const v = parseInt(el.value, 10); if (!Number.isNaN(v)) selected.push(v); });\n";
    echo "      } else {\n";
    echo "        const checked = document.querySelector('.form-check-input:checked');\n";
    echo "        if (checked) { const v = parseInt(checked.value, 10); if (!Number.isNaN(v)) selected.push(v); }\n";
    echo "      }\n";
    echo "      const fd2 = new FormData();\n";
    echo "      fd2.append('session_id', sessionIdVal);\n";
    echo "      fd2.append('question_id', currentQuestionId);\n";
    echo "      fd2.append('user_name', participantName);\n";
    echo "      selected.forEach(function(idx) { fd2.append('answer_indices[]', idx); });\n";
    echo "      try {\n";
    echo "        await fetch('api/submit_answer.php', { method: 'POST', body: fd2 });\n";
    echo "        fetchAnswerStats();\n";
    echo "        // afficher message de confirmation\n";
    echo "        const msgEl = document.getElementById('submitChoiceMsg');\n";
    echo "        if (msgEl) { msgEl.classList.remove('d-none'); setTimeout(() => msgEl.classList.add('d-none'), 1500); }\n";
    echo "        // désactiver les champs\n";
    echo "        document.querySelectorAll('.form-check-input').forEach(function(input) { input.disabled = true; });\n";
    echo "        // cacher le bouton Envoyer et montrer le bouton Modifier\n";
    echo "        submitBtn.classList.add('d-none');\n";
    echo "        if (modifyBtn) modifyBtn.classList.remove('d-none');\n";
    echo "      } catch (e) { console.error('Erreur lors de l\'envoi de la réponse', e); }\n";
    echo "    });\n";
    echo "  }\n";
    echo "  if (modifyBtn) {\n";
    echo "    modifyBtn.addEventListener('click', function() {\n";
    echo "      // réactiver les champs\n";
    echo "      document.querySelectorAll('.form-check-input').forEach(function(input) { input.disabled = false; });\n";
    echo "      // afficher le bouton Envoyer et masquer Modifier\n";
    echo "      if (submitBtn) submitBtn.classList.remove('d-none');\n";
    echo "      modifyBtn.classList.add('d-none');\n";
    echo "    });\n";
    echo "  }\n";
    echo "}\n";
    echo "</script>\n";
}
?>
</body>
</html>