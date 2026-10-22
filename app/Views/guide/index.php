<?php
use App\Finance;

// Scénarios de remboursement de l'exemple « carte de crédit 4'500.- à 12 % », calculés en direct.
$card = 4500.0;
$rate = 12.0;
$cardScenarios = [
    ['label' => '150.- / mois (minimum)', 'payment' => 150, 'extras' => []],
    ['label' => '150.- / mois + 1\'000.- en juin et décembre (13ᵉ)', 'payment' => 150, 'extras' => [6 => 1000, 12 => 1000]],
    ['label' => '400.- / mois', 'payment' => 400, 'extras' => []],
    ['label' => '600.- / mois', 'payment' => 600, 'extras' => []],
];
foreach ($cardScenarios as &$sc) {
    $sc['result'] = Finance::payoffSummary($card, $rate, (float) $sc['payment'], $sc['extras']);
}
unset($sc);
$base = $cardScenarios[0]['result'];
?>
<h1 class="h3 mb-2">Guide : prévoir toutes les dépenses de la famille</h1>
<p class="lead">Objectif : <strong>ne plus être coincé en fin de mois</strong>, lisser les grosses factures, intégrer les 13ᵉ salaires et rembourser la carte de crédit.</p>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['1', 'bi-list-check', 'Plan annuel', 'Saisir une fois tous les revenus et dépenses de l\'année, y compris les irréguliers.', url('planned')],
        ['2', 'bi-graph-up-arrow', 'Prévisions', 'Voir le solde prévu mois par mois et repérer les mois difficiles.', url('forecast')],
        ['3', 'bi-distribute-vertical', 'Lissage', 'Mettre de côté chaque mois 1/12ᵉ des grosses factures.', url('forecast/smoothing')],
        ['4', 'bi-bar-chart', 'Suivi', 'Importer les relevés, comparer réel et prévu (moyennes 3 / 6 / 12 mois).', url('forecast/analysis')],
    ] as [$n, $icon, $t, $d, $link]): ?>
        <div class="col-md-3"><a href="<?= $link ?>" class="card card-body h-100 text-reset text-decoration-none">
            <div class="d-flex align-items-center gap-2 mb-1"><span class="badge rounded-pill text-bg-primary"><?= $n ?></span><i class="bi <?= $icon ?>"></i><strong><?= $t ?></strong></div>
            <small class="text-muted"><?= $d ?></small></a></div>
    <?php endforeach; ?>
</div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Étape 1 — Le plan annuel (« Postes prévus »)</h2>
    <p>Menu <em>Budget & prévisions → Postes prévus</em>. Chaque poste a un <strong>montant à chaque fois</strong> et les <strong>mois</strong> où il tombe. Exemple d'une famille (montants fictifs, à remplacer par les vôtres) :</p>
    <div class="row g-4">
        <div class="col-lg-5">
            <h3 class="h6 text-success">Revenus</h3>
            <table class="table table-sm small">
                <thead><tr><th>Poste</th><th class="text-end">Montant</th><th>Mois</th></tr></thead>
                <tr><td>Salaire Fredy</td><td class="text-end">5'200.-</td><td>tous les mois</td></tr>
                <tr class="table-success"><td><strong>13ᵉ salaire Fredy (50 %)</strong></td><td class="text-end">2'600.-</td><td>juin + déc</td></tr>
                <tr><td>Salaire épouse</td><td class="text-end">3'000.-</td><td>tous les mois</td></tr>
                <tr class="table-success"><td><strong>13ᵉ salaire épouse</strong></td><td class="text-end">3'000.-</td><td>déc</td></tr>
                <tr><td>Allocations familiales</td><td class="text-end">530.-</td><td>tous les mois</td></tr>
            </table>
            <div class="alert alert-success small mb-0"><strong>13ᵉ versé en 2 fois :</strong> un seul poste, montant = la moitié du 13ᵉ, mois = juin + décembre (bouton « Juin + décembre »).<br>
                <strong>13ᵉ en décembre :</strong> montant = le 13ᵉ entier, mois = décembre uniquement.</div>
        </div>
        <div class="col-lg-7">
            <h3 class="h6 text-danger">Dépenses</h3>
            <div class="row">
                <div class="col-sm-6">
                    <table class="table table-sm small">
                        <thead><tr><th colspan="2">Tous les mois</th></tr></thead>
                        <tr><td>Loyer</td><td class="text-end">2'100.-</td></tr>
                        <tr><td>LAMal (4 personnes)</td><td class="text-end">1'150.-</td></tr>
                        <tr><td>Impôts (acomptes)</td><td class="text-end">750.-</td></tr>
                        <tr><td>Alimentation</td><td class="text-end">1'500.-</td></tr>
                        <tr><td>Crèche / extrascolaire</td><td class="text-end">600.-</td></tr>
                        <tr><td>Transports / voiture</td><td class="text-end">450.-</td></tr>
                        <tr><td>Loisirs & sorties</td><td class="text-end">400.-</td></tr>
                        <tr><td>Divers / Habillement / Télécom</td><td class="text-end">650.-</td></tr>
                    </table>
                </div>
                <div class="col-sm-6">
                    <table class="table table-sm small">
                        <thead><tr><th>Irrégulières</th><th class="text-end">Montant</th><th>Mois</th></tr></thead>
                        <tr class="table-warning"><td><strong>Assurance ménage / RC</strong></td><td class="text-end">2'000.-</td><td>déc</td></tr>
                        <tr><td>Assurance voiture</td><td class="text-end">1'100.-</td><td>mars</td></tr>
                        <tr><td>Décompte final impôts</td><td class="text-end">1'500.-</td><td>mai</td></tr>
                        <tr><td>Serafe</td><td class="text-end">335.-</td><td>mai</td></tr>
                        <tr><td>Vacances d'été</td><td class="text-end">3'000.-</td><td>juil</td></tr>
                        <tr><td>Dentiste / lunettes</td><td class="text-end">600.-</td><td>sept</td></tr>
                        <tr><td>Cadeaux de Noël</td><td class="text-end">800.-</td><td>déc</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <h3 class="h6 mt-2">Astuces de saisie</h3>
    <ul class="small mb-0">
        <li><strong>Ne saisissez pas les mensualités de dettes</strong> (carte de crédit, leasing) dans le plan : elles viennent automatiquement du module <a href="<?= url('debts') ?>">Dettes</a>.</li>
        <li>Vos <strong>factures récurrentes</strong> déjà saisies peuvent être ajoutées au plan en un clic (bandeau bleu en haut des postes prévus).</li>
        <li>Pour les <strong>dépenses courantes</strong> (alimentation, loisirs…), après avoir importé quelques mois de relevés, utilisez <a href="<?= url('forecast/analysis') ?>">Analyse → « Ajouter au plan »</a> : vos moyennes réelles deviennent des postes prévus.</li>
        <li>Une dépense qui s'arrête (fin de crèche, leasing) ? Indiquez « jusqu'à » ; une nouvelle dépense ? « à partir de ».</li>
        <li>Une fois par an (novembre, quand les nouvelles primes LAMal sont connues) : mettez à jour le plan.</li>
    </ul>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Étape 2 — Les prévisions : où est le problème ?</h2>
    <p>Dans l'exemple, les mois « normaux » laissent <strong>+980.-</strong> (8'730 de revenus − 7'600 de dépenses − 150 de carte de crédit). Mais certains mois, une grosse facture arrive sans 13ᵉ salaire :</p>
    <table class="table table-sm small" style="max-width: 560px">
        <thead><tr><th>Mois</th><th>Facture irrégulière</th><th class="text-end">Solde du mois</th></tr></thead>
        <tr><td>Mars</td><td>Assurance voiture 1'100.-</td><td class="text-end amount-expense">− 120.-</td></tr>
        <tr><td>Mai</td><td>Impôts 1'500.- + Serafe 335.-</td><td class="text-end amount-expense">− 855.-</td></tr>
        <tr class="table-danger"><td>Juillet</td><td>Vacances 3'000.-</td><td class="text-end amount-expense fw-semibold">− 2'020.-</td></tr>
        <tr class="table-success"><td>Décembre</td><td>Assurance ménage 2'000.- + Noël 800.-, mais deux 13ᵉ salaires (5'600.-)</td><td class="text-end amount-income">+ 3'780.-</td></tr>
    </table>
    <p class="mb-0">C'est exactement ce qui « coince » : <strong>l'argent de décembre est souvent dépensé quand arrive juillet</strong>. La page <a href="<?= url('forecast') ?>">Prévisions</a> colore ces mois en jaune (mois négatif) ou en rouge (solde sous zéro).</p>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Étape 3 — Le lissage : l'assurance ménage de 2'000.- devient 166.67 par mois</h2>
    <p>La page <a href="<?= url('forecast/smoothing') ?>">Lissage</a> additionne toutes les dépenses irrégulières de l'année et les divise par 12. Dans l'exemple : 9'335.- par an, soit <strong>777.92 à mettre de côté chaque mois</strong> (dont 166.67 pour l'assurance ménage).</p>
    <div class="row g-3">
        <div class="col-md-6">
            <h3 class="h6">La méthode du compte « réserve factures »</h3>
            <ol class="small">
                <li>Ouvrez un compte épargne séparé (UBS ou BCF), gratuit en général, et ajoutez-le dans <a href="<?= url('accounts') ?>">Comptes</a>.</li>
                <li>Versez-y <strong>les 13ᵉ salaires</strong> dès qu'ils arrivent (5'200.- + 3'000.- = 8'200.- par an, soit 683.33 par mois).</li>
                <li>Complétez par un <strong>ordre permanent</strong> de la différence : 777.92 − 683.33 = <strong>94.58 par mois</strong>.</li>
                <li>Payez <strong>toutes les factures irrégulières</strong> depuis ce compte (assurances, impôts, vacances…).</li>
            </ol>
        </div>
        <div class="col-md-6">
            <h3 class="h6">Résultat</h3>
            <p class="small">Le compte salaire voit chaque mois exactement la même chose : salaires − dépenses courantes − 94.58 − carte de crédit. Il reste <strong>885.41 par mois</strong> (la « marge réelle »), <strong>tous les mois, juillet compris</strong>.</p>
            <p class="small mb-0">La page Lissage simule le solde du compte réserve sur 12 mois et indique s'il faut un <strong>montant de départ</strong> (quand une grosse facture tombe avant que la réserve soit constituée).</p>
        </div>
    </div>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Liquider la carte de crédit de 4'500.-</h2>
    <ol>
        <li>Dans <a href="<?= url('debts/edit') ?>">Dettes → Nouvelle dette</a> : « Carte de crédit », montant <strong>4'500.-</strong>, taux annuel (indiqué sur votre relevé ; souvent entre 10 et 14 %), mensualité actuelle.</li>
        <li>Ouvrez la dette : le <strong>simulateur</strong> compare les mensualités, permet d'ajouter un versement extra à certains mois (13ᵉ) et calcule la mensualité pour finir en N mois.</li>
    </ol>
    <p class="small text-muted">Exemple calculé par l'application, 4'500.- à <?= $rate ?> % :</p>
    <div class="table-responsive"><table class="table table-sm" style="max-width: 760px">
        <thead><tr><th>Stratégie</th><th class="text-end">Durée</th><th class="text-end">Intérêts payés</th><th class="text-end">Économie</th></tr></thead>
        <?php foreach ($cardScenarios as $i => $sc): $r = $sc['result']; ?>
            <tr class="<?= $i === 0 ? 'table-danger' : '' ?>"><td><?= e($sc['label']) ?></td>
                <td class="text-end"><?= $r ? $r['months'] . ' mois' : '—' ?></td>
                <td class="text-end amount"><?= $r ? money($r['interest']) : '—' ?></td>
                <td class="text-end amount amount-income"><?= $r && $base && $i > 0 ? money($base['interest'] - $r['interest']) : '' ?></td></tr>
        <?php endforeach; ?>
    </table></div>
    <h3 class="h6">La stratégie conseillée</h3>
    <ul class="mb-0">
        <li><strong>Arrêter d'ajouter des achats</strong> sur la carte tant qu'elle n'est pas soldée (payer par carte de débit ou TWINT). Sinon la dette ne baisse jamais.</li>
        <li><strong>Mettre la marge réelle sur la carte</strong> : le lissage montre combien il reste chaque mois (885.- dans l'exemple). Passer de 150.- à 600.- par mois solde la carte en 8 mois au lieu de 3 ans.</li>
        <li><strong>Une partie du 13ᵉ</strong> sur la carte accélère encore, mais seulement si la réserve factures reste suffisante.</li>
        <li>Une fois soldée : <strong>continuer le même virement vers l'épargne</strong> (fonds d'urgence de 3 mois de charges, puis 3ᵉ pilier). Vous êtes déjà habitués à vivre sans cet argent.</li>
        <li>Plusieurs dettes ? La page <a href="<?= url('debts') ?>">Dettes</a> indique l'ordre à suivre (taux le plus élevé d'abord : la carte de crédit est presque toujours la première).</li>
    </ul>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Les abonnements (Netflix, Spotify, téléphone…)</h2>
    <p>Pris un par un, ils semblent petits. Additionnés sur une année, ils pèsent souvent plusieurs centaines de francs. Le menu <a href="<?= url('subscriptions') ?>">Abonnements</a> les rassemble.</p>
    <ol>
        <li><strong>Créez votre carte de crédit comme compte</strong> : <a href="<?= url('accounts/edit') ?>">Comptes → Nouveau compte</a>, type « Carte de crédit ». Importez-y aussi les relevés de la carte (CSV ou camt).</li>
        <li><strong>Ajoutez vos abonnements</strong>, ou laissez l'application les <strong>détecter</strong> dans vos relevés (paiements qui reviennent chaque mois avec le même montant) et cliquez sur « Ajouter ».</li>
        <li>Indiquez avec quoi chacun est <strong>payé</strong> (carte de crédit, compte UBS…) et le <strong>mot-clé</strong> du relevé (ex. « netflix »).</li>
    </ol>
    <p>Ensuite, l'application :</p>
    <ul>
        <li>affiche le coût <strong>par mois et par an</strong> (Netflix à 21.90 = 262.80 par an), et la part prélevée sur la carte de crédit ;</li>
        <li>signale les <strong>hausses de prix</strong> (« Netflix : 21.90 prélevé au lieu de 18.90 ») et les prélèvements qui ont disparu ;</li>
        <li>prévient avant les <strong>renouvellements annuels</strong>, le bon moment pour résilier ;</li>
        <li>calcule ce que vos <strong>résiliations</strong> vous ont fait économiser ;</li>
        <li>intègre automatiquement les abonnements aux <a href="<?= url('forecast') ?>">prévisions</a> et au <a href="<?= url('forecast/smoothing') ?>">lissage</a>. <strong>Ne les saisissez donc pas aussi en postes prévus.</strong></li>
    </ul>
    <div class="alert alert-warning small mb-0"><strong>Carte de crédit à rembourser ?</strong> Chaque abonnement prélevé sur la carte s'ajoute à la dette si le relevé n'est pas payé en entier, et coûte donc environ 12 % d'intérêts en plus. Pendant le remboursement : passez-les sur la carte de débit ou TWINT, ou résiliez-les.</div>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Score financier, impôts et calendrier</h2>
    <ul class="mb-0">
        <li><strong><a href="<?= url('score') ?>">Score financier</a></strong> : une note sur 100 calculée sur 9 critères (taux d'épargne, fonds d'urgence, dettes, logement, budget, factures, prévisions, 3ᵉ pilier, suivi). Elle affiche vos 3 priorités et sa progression mois après mois.</li>
        <li><strong>Taux d'épargne</strong> : la part des revenus qui reste après les dépenses, affichée sur l'accueil et dans le budget du mois. Visez au moins 10 %, idéalement 20 %.</li>
        <li><strong>Objectifs d'épargne</strong> : la date d'arrivée estimée au rythme de vos versements des 6 derniers mois, en rouge si vous êtes en retard sur la date visée.</li>
        <li><strong><a href="<?= url('taxes') ?>">Impôts</a></strong> : saisissez une fois votre dernier avis de taxation (revenus nets et total des impôts). L'application calcule votre taux réel, estime les impôts de l'année, compare avec ce que vous avez prévu et chiffre l'économie possible avec le 3a. Une liste vous aide à rassembler les justificatifs des déductions.</li>
        <li><strong><a href="<?= url('calendar') ?>">Calendrier</a></strong> : tous les prélèvements et rentrées du mois, jour par jour, avec le solde prévu. Pratique pour vérifier qu'aucune grosse facture ne tombe avant le salaire.</li>
    </ul>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Étape 4 — La routine (15 minutes par mois)</h2>
    <div class="row">
        <div class="col-md-6">
            <h3 class="h6">Chaque mois, après le salaire</h3>
            <ol class="small">
                <li><a href="<?= url('import') ?>">Importer</a> les relevés UBS et BCF du mois.</li>
                <li>Classer les opérations « à classer » (et ajouter une <a href="<?= url('categories') ?>">règle</a> pour qu'elles se classent seules la prochaine fois).</li>
                <li>Vérifier le <a href="<?= url('budgets') ?>">budget du mois</a> : quelles catégories dépassent ?</li>
                <li>Regarder les <a href="<?= url('forecast') ?>">prévisions</a> des 3 prochains mois (aussi sur le tableau de bord).</li>
                <li>Enregistrer le remboursement de la carte dans <a href="<?= url('debts') ?>">Dettes</a>.</li>
            </ol>
        </div>
        <div class="col-md-6">
            <h3 class="h6">Tous les 3 à 6 mois</h3>
            <ol class="small">
                <li>Ouvrir l'<a href="<?= url('forecast/analysis') ?>">Analyse 3 / 6 / 12 mois</a> : vos dépenses réelles correspondent-elles au plan ?</li>
                <li>Ajuster les postes prévus (ex. alimentation réelle 1'650.- au lieu de 1'500.-).</li>
                <li>Remplir le budget du mois avec la moyenne sur 6 mois (bouton dans l'Analyse).</li>
            </ol>
            <h3 class="h6">Budget du mois ou postes prévus ?</h3>
            <p class="small mb-0"><strong>Postes prévus</strong> = le plan de l'année (pour les prévisions et le lissage). <strong>Budget du mois</strong> = l'enveloppe de chaque catégorie pour suivre les dépenses réelles du mois en cours.</p>
        </div>
    </div>
</div></div>
