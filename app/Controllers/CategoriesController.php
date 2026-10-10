<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;

final class CategoriesController
{
    public function index(): void
    {
        $rules = db()->query('SELECT r.*, c.name AS category_name, c.color, c.type AS category_type
            FROM category_rules r JOIN categories c ON c.id = r.category_id ORDER BY r.keyword')->fetchAll();
        $usage = [];
        foreach (db()->query('SELECT category_id, COUNT(*) AS n FROM transactions WHERE category_id IS NOT NULL GROUP BY category_id') as $row) {
            $usage[(int) $row['category_id']] = (int) $row['n'];
        }
        view('categories/index', [
            'title'      => 'Catégories & règles',
            'categories' => all_categories(),
            'rules'      => $rules,
            'usage'      => $usage,
            'uncategorized' => (int) db()->query("SELECT COUNT(*) FROM transactions WHERE category_id IS NULL AND type <> 'transfer'")->fetchColumn(),
        ]);
    }

    public function edit(): void
    {
        $category = ['id' => null, 'name' => '', 'type' => input('type') === 'income' ? 'income' : 'expense', 'color' => '#6c757d'];
        if ($id = int_input('id')) {
            $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch() ?: abort(404);
        }
        view('categories/edit', ['title' => 'Catégorie', 'category' => $category]);
    }

    public function save(): void
    {
        $id = int_input('id');
        $name = (string) input('name');
        $type = input('type') === 'income' ? 'income' : 'expense';
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) input('color')) ? input('color') : '#6c757d';
        if ($name === '') {
            flash('danger', 'Le nom est obligatoire.');
            redirect('categories');
        }
        try {
            if ($id) {
                db()->prepare('UPDATE categories SET name = ?, type = ?, color = ? WHERE id = ?')->execute([$name, $type, $color, $id]);
            } else {
                db()->prepare('INSERT INTO categories (name, type, color) VALUES (?, ?, ?)')->execute([$name, $type, $color]);
            }
            flash('success', 'Catégorie enregistrée.');
        } catch (\PDOException) {
            flash('danger', 'Une catégorie portant ce nom existe déjà.');
        }
        redirect('categories');
    }

    public function delete(): void
    {
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([int_input('id')]);
        flash('success', 'Catégorie supprimée (les transactions concernées deviennent « sans catégorie »).');
        redirect('categories');
    }

    public function saveRule(): void
    {
        $keyword = (string) input('keyword');
        $categoryId = int_input('category_id');
        if ($keyword !== '' && $categoryId) {
            db()->prepare('INSERT INTO category_rules (keyword, category_id) VALUES (?, ?)')->execute([$keyword, $categoryId]);
            flash('success', 'Règle ajoutée.');
        }
        redirect('categories');
    }

    public function deleteRule(): void
    {
        db()->prepare('DELETE FROM category_rules WHERE id = ?')->execute([int_input('id')]);
        redirect('categories');
    }

    /** Applique les règles à toutes les transactions sans catégorie. */
    public function applyRules(): void
    {
        $rules = Finance::rules();
        $update = db()->prepare('UPDATE transactions SET category_id = ? WHERE id = ?');
        $count = 0;
        $rows = db()->query("SELECT id, description, type FROM transactions WHERE category_id IS NULL AND type <> 'transfer'");
        foreach ($rows->fetchAll() as $t) {
            if ($cid = Finance::guessCategory($t['description'], $t['type'], $rules)) {
                $update->execute([$cid, $t['id']]);
                $count++;
            }
        }
        flash('success', "$count transaction(s) catégorisée(s) automatiquement.");
        redirect('categories');
    }
}
