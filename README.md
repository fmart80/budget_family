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
| **Budget** | Budget mensuel par catégorie, prévu vs réel, copie du mois précédent |
| **Factures** | Échéances, retards, pièces jointes (PDF/JPG/PNG), factures récurrentes (mensuelle → annuelle) recréées automatiquement au paiement |
| **Dettes** | Solde restant, échéancier avec intérêts, date de fin estimée, stratégies avalanche et boule de neige |
| **Épargne** | Objectifs (fonds d'urgence, vacances, 3ᵉ pilier…), progression, montant à épargner par mois, virement automatique vers le compte épargne |
| **Utilisateurs** | Plusieurs membres de la famille (rôles administrateur et membre), mots de passe chiffrés, blocage après 5 tentatives échouées |

Les montants s'affichent au format suisse (`CHF 1'234.50`) et les dates au format `31.12.2026`.

## Installation sur KreativMedia (Plesk)

### 1. Base de données
Dans Plesk, ouvrez **Bases de données → Ajouter une base de données**. Choisissez MySQL/MariaDB et créez un utilisateur. Notez le nom de la base, l'utilisateur et le mot de passe. L'hôte est en général `localhost`.

### 2. Version de PHP
Dans **Sites Web & Domaines → Paramètres PHP**, sélectionnez **PHP 8.2**. Les extensions `pdo_mysql`, `mbstring` et `dom` sont nécessaires et normalement actives. `zip` est facultative et sert seulement à importer un relevé camt en ZIP.

### 3. Envoi des fichiers (SFTP)
Envoyez **tout le contenu du dépôt** dans le dossier du site, par exemple `/httpdocs/` :

```
httpdocs/
├── app/          ← code (accès web bloqué)
├── config/       ← configuration (accès web bloqué)
├── database/     ← schéma SQL (accès web bloqué)
├── public/       ← seule partie publique
├── storage/      ← pièces jointes (accès web bloqué)
└── index.php
```

### 4. Racine du site (important)
Dans **Sites Web & Domaines → Paramètres d'hébergement**, réglez la **racine des documents** sur `httpdocs/public`. Les fichiers de configuration et les factures jointes ne sont alors jamais accessibles depuis le web.

> Si vous ne pouvez pas modifier ce réglage, l'application fonctionne quand même : `index.php` redirige vers `public/`, et chaque dossier sensible contient un `.htaccess` qui en interdit l'accès.

### 5. Configuration
Dans le **Gestionnaire de fichiers** de Plesk, copiez `config/config.example.php` en `config/config.php`, puis renseignez :
- les accès à la base de données (étape 1) ;
- `setup_key` : une phrase secrète longue, demandée une seule fois pendant l'installation.

### 6. Installation
Ouvrez votre site dans le navigateur. Saisissez la `setup_key`, cliquez sur **Installer la base de données**, puis créez votre compte administrateur.

### 7. HTTPS
Activez un certificat **Let's Encrypt** gratuit dans Plesk (**SSL/TLS**). Décommentez ensuite les lignes de redirection HTTPS dans `public/.htaccess`.

### 8. Dossier des pièces jointes
`storage/uploads/` doit être accessible en écriture par PHP. C'est le cas par défaut sur Plesk. En cas d'erreur à l'envoi d'une facture, donnez les droits `755` à ce dossier.

## Premiers pas
1. **Comptes** : créez vos comptes (« UBS – Compte privé », « BCF – Épargne », « Espèces »…) avec leur solde de départ.
2. **Import** : importez un premier relevé UBS ou BCF (voir ci-dessous).
3. **Règles** : dans *Catégories & règles*, ajoutez des mots-clés (`Coop`, `Migros` → Alimentation ; `CSS`, `Groupe Mutuel` → Assurance maladie ; `Swisscom` → Télécom…), puis cliquez sur *Appliquer*.
4. **Budget** : définissez un montant par catégorie pour le mois.
5. **Factures, dettes, épargne** : saisissez vos factures récurrentes, vos crédits ou leasings et vos objectifs d'épargne.
6. **Utilisateurs** (*Plus → Utilisateurs*) : ajoutez les autres membres de la famille.

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
