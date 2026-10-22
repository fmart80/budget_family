# Budget Famille

Application web en PHP pour gérer le budget d'une famille : comptes, transactions, budget mensuel, factures, dettes et épargne. Elle est conçue pour un hébergement mutualisé **Plesk** (KreativMedia) avec PHP 8.2 et MySQL/MariaDB, sans Composer ni étape de compilation.

## Fonctionnalités

| Module | Ce qu'il fait |
|---|---|
| **Tableau de bord** | Avoirs totaux, revenus et dépenses du mois, progression du budget, factures à venir, graphiques sur 12 mois et par catégorie |
| **Comptes** | Compte courant, épargne, espèces, carte… Le solde est calculé automatiquement |
| **Transactions** | Dépenses, revenus et virements entre comptes. Filtres, recherche, catégorisation groupée, export CSV (Excel) |
| **Import bancaire** | CSV **UBS** (FR/DE) et **BCF**, XML ISO 20022 **camt.053/054** (ou ZIP). Aperçu avant import, doublons détectés, catégorisation automatique |
| **Catégories & règles** | Catégories adaptées aux ménages suisses (LAMal, impôts, Serafe…). Règles « mot-clé → catégorie » |
| **Budget** | Budget mensuel par catégorie, prévu vs réel, copie du mois précédent ou remplissage avec la moyenne des 3 / 6 / 12 derniers mois |
| **Postes prévus** | Plan annuel des revenus et dépenses, y compris irréguliers : 13ᵉ salaire (en une ou deux fois), assurance annuelle, impôts, vacances… |
| **Prévisions** | Solde prévu mois par mois sur 6 à 24 mois, mensualités de dettes comprises, avec repérage des mois difficiles |
| **Lissage** | Montant à mettre de côté chaque mois pour les dépenses irrégulières, marge mensuelle réelle, plan de compte « réserve factures » |
| **Analyse** | Dépenses moyennes par catégorie sur 3, 6 et 12 mois, comparées au plan et au mois en cours |
| **Factures** | Échéances, retards, pièces jointes (PDF/JPG/PNG), factures récurrentes (mensuelle → annuelle) recréées automatiquement au paiement |
| **Score financier** | Note sur 100 (9 critères : épargne, fonds d'urgence, dettes, logement, budget, factures, prévisions, 3ᵉ pilier, suivi), priorités et historique ; taux d'épargne sur l'accueil et le budget |
| **Impôts** | Estimation à partir du dernier avis de taxation (taux réel), écart avec le plan, économie possible avec le 3a, liste des déductions à rassembler |
| **Calendrier** | Prélèvements et rentrées du mois jour par jour (factures, abonnements, postes prévus, dettes) avec solde prévu |
| **Abonnements** | Netflix, Spotify, téléphone… : coût par mois et par an, part sur la carte de crédit, prochains prélèvements, détection automatique dans les relevés, alertes de hausse de prix, économies réalisées grâce aux résiliations |
| **Dettes** | Solde restant, échéancier avec intérêts, date de fin estimée, stratégies avalanche et boule de neige, simulateur (mensualité, versements extra avec le 13ᵉ, « finir en N mois ») |
| **Épargne** | Objectifs (fonds d'urgence, vacances, 3ᵉ pilier…), progression, montant à épargner par mois, date d'arrivée estimée au rythme actuel, virement automatique vers le compte épargne |
| **Utilisateurs** | Plusieurs membres de la famille (rôles administrateur et membre), mots de passe chiffrés, blocage après 5 tentatives échouées |

Les montants s'affichent au format suisse (`CHF 1'234.50`) et les dates au format `31.12.2026`.

## Installation sur KreativMedia (Plesk)

### 1. Base de données
Dans Plesk, ouvrez **Bases de données → Ajouter une base de données**. Choisissez MySQL/MariaDB et créez un utilisateur. Notez le nom de la base, l'utilisateur et le mot de passe. L'hôte est en général `localhost`.

### 2. Version de PHP
Dans **Sites Web & Domaines → Paramètres PHP**, sélectionnez **PHP 8.2**. Les extensions `pdo_mysql`, `mbstring` et `dom` sont nécessaires et normalement actives. `zip` est facultative et sert seulement à importer un relevé camt en ZIP.

### 3. Envoi des fichiers (FTP)
L'application s'installe dans un sous-dossier, par exemple `https://votre-domaine.ch/budget/`. Créez le dossier `/httpdocs/budget/` et envoyez-y **tout le contenu du dépôt**, y compris les fichiers cachés `.htaccess` :

```
httpdocs/
├── (votre site actuel, inchangé)
└── budget/
    ├── .htaccess     ← redirige les requêtes vers public/ (important)
    ├── app/          ← code (accès web bloqué)
    ├── config/       ← configuration (accès web bloqué)
    ├── database/     ← schéma SQL (accès web bloqué)
    ├── public/       ← seule partie publique
    ├── storage/      ← pièces jointes (accès web bloqué)
    └── index.php
```

> Vérifiez que votre client FTP affiche et envoie les fichiers cachés (FileZilla : *Serveur → Forcer l'affichage des fichiers cachés*).

### 4. Accès à l'application
Aucun réglage de Plesk n'est nécessaire : le fichier `budget/.htaccess` sert automatiquement le contenu de `public/`. L'application répond à `https://votre-domaine.ch/budget/` et tout le reste (`config/`, `app/`, `storage/`…) est inaccessible depuis le web.

- Le cookie de session est limité à `/budget/` : il n'interfère pas avec le reste du site.
- Si les pages s'affichent sans mise en forme ou renvoient une erreur 404, décommentez la ligne `RewriteBase /budget/` dans `budget/.htaccess`.
- Si `.htaccess` n'est pas pris en compte (serveur nginx seul), le fichier `budget/index.php` redirige vers `budget/public/` : l'application fonctionne alors à l'adresse `/budget/public/`.

### 5. Configuration
Dans le **Gestionnaire de fichiers** de Plesk, copiez `config/config.example.php` en `config/config.php`, puis renseignez :
- les accès à la base de données (étape 1) ;
- `setup_key` : une phrase secrète longue, demandée une seule fois pendant l'installation.

### 6. Installation
Ouvrez `https://votre-domaine.ch/budget/` dans le navigateur. Saisissez la `setup_key`, cliquez sur **Installer la base de données**, puis créez votre compte administrateur.

### 7. HTTPS
Activez un certificat **Let's Encrypt** gratuit dans Plesk (**SSL/TLS**). Décommentez ensuite les deux lignes « Forcer HTTPS » dans `budget/.htaccess`.

### 8. Dossier des pièces jointes
`storage/uploads/` doit être accessible en écriture par PHP. C'est le cas par défaut sur Plesk. En cas d'erreur à l'envoi d'une facture, donnez les droits `755` à ce dossier.

## Premiers pas
1. **Comptes** : créez vos comptes (« UBS – Compte privé », « BCF – Épargne », « Espèces »…) avec leur solde de départ.
2. **Import** : importez un premier relevé UBS ou BCF (voir ci-dessous).
3. **Règles** : dans *Catégories & règles*, ajoutez des mots-clés (`Coop`, `Migros` → Alimentation ; `CSS`, `Groupe Mutuel` → Assurance maladie ; `Swisscom` → Télécom…), puis cliquez sur *Appliquer*.
4. **Budget** : définissez un montant par catégorie pour le mois.
5. **Factures, dettes, épargne** : saisissez vos factures récurrentes, vos crédits ou leasings et vos objectifs d'épargne.
6. **Utilisateurs** (*Plus → Utilisateurs*) : ajoutez les autres membres de la famille.

## Mise à jour d'une installation existante

### Automatique (GitHub Actions)
À chaque mise à jour de la branche `main` (fusion d'une pull request), GitHub teste l'application puis l'envoie par FTP sur KreativMedia (`.github/workflows/deploy.yml`). Les tests sont aussi lancés sur chaque pull request, avant la fusion.

**Configuration (une seule fois)** : dans GitHub, *Settings → Secrets and variables → Actions → New repository secret*, créez :

| Secret | Valeur |
|---|---|
| `FTP_HOST` | serveur FTP (le même que dans FileZilla), ex. `votre-domaine.ch` |
| `FTP_USER` | utilisateur FTP |
| `FTP_PASSWORD` | mot de passe FTP |
| `FTP_PATH` | dossier de l'application, ex. `httpdocs/budget` (chemin tel qu'affiché dans FileZilla à la connexion) |
| `FTP_PORT` | facultatif, `21` par défaut |
| `FTP_SECURITY` | facultatif : `tls` (défaut), `tls-noverify` ou `none` (voir ci-dessous) |

**Chiffrement de la connexion (`FTP_SECURITY`)**
- `tls` (par défaut) : FTP chiffré (FTPS) et certificat du serveur vérifié. C'est le plus sûr.
- `tls-noverify` : FTP chiffré, mais sans vérifier le certificat. À utiliser si le déploiement échoue avec « Certificate verification… not trusted », ce qui arrive souvent sur Plesk, où le certificat FTP est au nom du serveur et non de votre domaine. Autre solution : mettre dans `FTP_HOST` le nom du serveur figurant sur ce certificat (FileZilla l'affiche à la première connexion).
- `none` : FTP non chiffré. Le mot de passe circule en clair sur Internet. À réserver au cas où le serveur refuse le chiffrement.

**À savoir**
- Les fichiers `config/config.php` et `storage/uploads/` ne sont **jamais envoyés ni supprimés**. Le dossier `tests/` n'est pas envoyé.
- Tant que les secrets ne sont pas créés, le déploiement est simplement ignoré (avertissement dans l'onglet *Actions*).
- Pour relancer un déploiement manuellement : onglet *Actions → Tests et déploiement → Run workflow* (branche `main`).
- Suivi : onglet *Actions* du dépôt. Une coche verte signifie que le site est à jour.
- Les fichiers supprimés du dépôt ne sont pas supprimés du serveur. C'est sans danger, car l'application ne les utilise plus.

### Manuelle (FTP)
Envoyez les nouveaux fichiers en remplaçant les anciens, **sans toucher à `config/config.php` ni à `storage/uploads/`**.

Dans les deux cas, les nouvelles tables sont créées automatiquement au premier chargement d'une page (fichiers `database/migrations/`), sans perte de données.

## Guide d'utilisation
Le menu *Plus → Guide & exemples* explique, avec un exemple chiffré, comment saisir le plan annuel (13ᵉ salaire, assurance ménage…), lire les prévisions, mettre en place le lissage et rembourser une carte de crédit.

## Import des relevés bancaires

| Banque | Format conseillé | Remarques |
|---|---|---|
| UBS | CSV (e-banking) ou camt.053 | Colonnes reconnues automatiquement, en français comme en allemand. Un ordre groupé est importé comme une seule opération (son total) |
| BCF | camt.053 (XML) ou CSV | Le camt.053 est le plus fiable. Pour un CSV, vérifiez à l'aperçu que les colonnes Date, Montant ou Débit/Crédit et Libellé sont correctes et corrigez-les si besoin |

- Rien n'est enregistré avant d'avoir cliqué sur **Importer la sélection**.
- Une opération déjà importée est marquée **doublon** et ignorée : réimporter un relevé qui chevauche le précédent ne crée pas de doublon.
- Si vous importez vos relevés, choisissez « Ne pas créer de transaction » quand vous marquez une facture comme payée : le débit viendra déjà du relevé.

## Sauvegardes
Plesk propose des sauvegardes automatiques (**Gestionnaire de sauvegarde**). Vous pouvez aussi exporter la base depuis **phpMyAdmin**. Sauvegardez aussi le dossier `storage/uploads/` (les factures jointes).

## Sécurité
- **Version de PHP :** gardez PHP à jour dans *Plesk → Paramètres PHP*. Un bandeau rouge, visible uniquement par les administrateurs, s'affiche si la version du serveur a une faille connue (ex. PHP2Shell, CVE-2026-109618 / CVE-2026-109619, corrigée dans PHP 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12) ou approche de sa fin de support. PHP 8.2 ne reçoit plus de correctifs après le 31.12.2026 : préférez PHP 8.4.
- **Durcissement :** `public/.user.ini` (et `public/.htaccess` pour mod_php) désactive `session.upload_progress` (inutilisé par l'application) et limite la taille des requêtes. Cela réduit la surface d'attaque, mais **ne remplace pas la mise à jour de PHP**.
- **Contrôle d'intégrité :** à chaque déploiement, GitHub Actions liste les fichiers présents sur le serveur mais absents du dépôt, avec un avertissement s'il s'agit de fichiers PHP ou de configuration (fichier ancien ou déposé par un tiers). Rien n'est supprimé automatiquement.
- **Tests :** l'application est testée sur PHP 8.2, 8.3, 8.4 et 8.5 à chaque pull request.

## Développement local
```bash
cp config/config.example.php config/config.php   # puis adapter
php -S localhost:8080 -t public
php tests/run.php                                 # tests des imports et des calculs
```

## Structure du code
- `public/index.php` : point d'entrée et routes (`index.php?p=module/action`)
- `app/Controllers/` : un contrôleur par module
- `app/Views/` : gabarits HTML (Bootstrap 5)
- `app/Import/` : lecture des CSV et des fichiers camt
- `app/Finance.php` : soldes, totaux, calculs d'échéancier
- `database/schema.sql`, `database/seed.sql` : structure de la base et catégories par défaut
