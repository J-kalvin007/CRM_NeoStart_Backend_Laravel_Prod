<?php
$content = file_get_contents('test_output.txt');
$content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
$pos = strpos($content, 'Erreur changement mot de passe');
if ($pos !== false) {
    echo substr($content, $pos, 500);
} else {
    echo "Pas d'erreur trouvée.";
}
