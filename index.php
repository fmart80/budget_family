<?php
// Si la racine du site (document root) pointe sur ce dossier plutôt que sur /public,
// on redirige vers l'application. Recommandé : régler le document root sur /public dans Plesk.
header('Location: public/');
exit;
