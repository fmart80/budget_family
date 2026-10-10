<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;
use App\Subscriptions;

final class SubscriptionsController
{
    private function find(?int $id): array
    {
        $stmt = db()->prepare('SELECT * FROM subscriptions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: abort(404, 'Abonnement introuvable');
    }

    /** Tableau de bord des abonnements. */
    public function index(): void
    {
        $subs = Subscriptions::all();
        $active = array_values(array_filter($subs, fn ($s) => $s['status'] === 'active'));
        $paused = array_values(array_filter($subs, fn ($s) => $s['status'] === 'paused'));
        $cancelled = array_values(array_filter($subs, fn ($s) => $s['status'] === 'cancelled'));

        usort($active, fn ($a, $b) => $b['monthly'] <=> $a['monthly']);
        $upcoming = array_filter($active, fn ($s) => $s['next_date'] <= date('Y-m-d', strtotime('+30 days')));
        usort($upcoming, fn ($a, $b) => $a['next_date'] <=> $b['next_date']);
        $renewals = array_filter($active, fn ($s) => $s['cycle'] !== 'monthly'
            && $s['next_date'] <= date('Y-m-d', strtotime('+60 days')));

        $byAccount = [];
        foreach ($active as $s) {
            $label = $s['account_name'] ?? 'Moyen de paiement non précisé';
            $byAccount[$label] = ($byAccount[$label] ?? 0) + $s['monthly'];
        }
        arsort($byAccount);

        $savings = 0.0;
        foreach ($cancelled as &$c) {
            $since = $c['cancelled_at'] ?: date('Y-m-d');
            $d = (new \DateTimeImmutable($since))->diff(new \DateTimeImmutable());
            $c['saved'] = round($c['monthly'] * ($d->y * 12 + $d->m), 2);
            $savings += $c['saved'];
        }
        unset($c);

        $income = (float) db()->query("SELECT COALESCE(SUM(amount * (CHAR_LENGTH(months) - CHAR_LENGTH(REPLACE(months, ',', '')) + 1)), 0) / 12
            FROM planned_items WHERE type = 'income' AND is_active = 1")->fetchColumn();
        $hasTransactions = (bool) db()->query('SELECT 1 FROM transactions LIMIT 1')->fetchColumn();

        view('subscriptions/index', [
            'title'       => 'Abonnements',
            'active'      => $active,
            'paused'      => $paused,
            'cancelled'   => $cancelled,
            'upcoming'    => $upcoming,
            'renewals'    => $renewals,
            'byAccount'   => $byAccount,
            'savings'     => $savings,
            'monthly'     => array_sum(array_column($active, 'monthly')),
            'onCard'      => array_sum(array_map(fn ($s) => $s['on_card'] ? $s['monthly'] : 0, $active)),
            'income'      => $income,
            'alerts'      => Subscriptions::alerts($active),
            'suggestions' => $hasTransactions ? Subscriptions::detect($subs) : [],
            'hasTransactions' => $hasTransactions,
            'hasCardAccount'  => (bool) array_filter(all_accounts(), fn ($a) => $a['type'] === 'carte'),
        ]);
    }

    public function edit(): void
    {
        $cardAccount = array_values(array_filter(all_accounts(), fn ($a) => $a['type'] === 'carte'))[0]['id'] ?? null;
        $sub = ['id' => null, 'name' => (string) input('name'), 'amount' => (string) input('amount'), 'cycle' => 'monthly',
            'anchor_date' => valid_date((string) input('date')) ?? date('Y-m-d'),
            'account_id' => int_input('account_id') ?? $cardAccount,
            'category_id' => Finance::categoryIdByName('Abonnements & streaming'), 'keyword' => (string) input('keyword'),
            'owner' => '', 'url' => '', 'notes' => '', 'status' => 'active'];
        if ($id = int_input('id')) {
            $sub = $this->find($id);
        }
        view('subscriptions/edit', ['title' => 'Abonnement', 'sub' => $sub,
            'accounts' => all_accounts(false), 'categories' => all_categories('expense'),
            'fromSuggestion' => !$sub['id'] && input('keyword') !== '']);
    }

    public function save(): void
    {
        $id = int_input('id');
        $cycle = array_key_exists((string) input('cycle'), Subscriptions::CYCLES) ? input('cycle') : 'monthly';
        $url = (string) input('url');
        $data = [
            'name'        => (string) input('name'),
            'amount'      => abs(parse_amount(input('amount'))),
            'cycle'       => $cycle,
            'anchor_date' => valid_date((string) input('anchor_date')) ?? date('Y-m-d'),
            'account_id'  => int_input('account_id'),
            'category_id' => int_input('category_id'),
            'keyword'     => mb_strtolower((string) input('keyword')) ?: null,
            'owner'       => input('owner') ?: null,
            'url'         => preg_match('#^https?://#i', $url) ? $url : null,
            'notes'       => input('notes') ?: null,
        ];
        if ($data['name'] === '' || $data['amount'] <= 0) {
            flash('danger', 'Le nom et le montant sont obligatoires.');
            redirect('subscriptions/edit', $id ? ['id' => $id] : []);
        }
        if ($id) {
            $old = $this->find($id);
            if (abs((float) $old['amount'] - $data['amount']) >= 0.01) {
                $data['previous_amount'] = $old['amount'];
                $data['amount_changed_at'] = date('Y-m-d');
            }
            $set = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($data)));
            db()->prepare("UPDATE subscriptions SET $set WHERE id = ?")->execute([...array_values($data), $id]);
        } else {
            $cols = array_keys($data);
            db()->prepare('INSERT INTO subscriptions (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
                ->execute(array_values($data));
        }

        if (isset($_POST['create_rule']) && $data['category_id']) {
            $keyword = $data['keyword'] ?: mb_strtolower($data['name']);
            $exists = db()->prepare('SELECT COUNT(*) FROM category_rules WHERE keyword = ?');
            $exists->execute([$keyword]);
            if (!(int) $exists->fetchColumn()) {
                db()->prepare('INSERT INTO category_rules (keyword, category_id) VALUES (?, ?)')->execute([$keyword, $data['category_id']]);
            }
        }
        flash('success', 'Abonnement enregistré.');
        redirect('subscriptions');
    }

    public function delete(): void
    {
        db()->prepare('DELETE FROM subscriptions WHERE id = ?')->execute([int_input('id')]);
        flash('success', 'Abonnement supprimé.');
        redirect('subscriptions');
    }

    /** Résilier, suspendre ou réactiver. */
    public function status(): void
    {
        $sub = $this->find(int_input('id'));
        $status = in_array(input('status'), ['active', 'paused', 'cancelled'], true) ? input('status') : 'active';
        db()->prepare('UPDATE subscriptions SET status = ?, cancelled_at = ? WHERE id = ?')
            ->execute([$status, $status === 'cancelled' ? date('Y-m-d') : null, $sub['id']]);
        flash('success', match ($status) {
            'cancelled' => "« {$sub['name']} » résilié : bravo, c'est autant d'économisé !",
            'paused'    => "« {$sub['name']} » suspendu.",
            default     => "« {$sub['name']} » réactivé.",
        });
        redirect('subscriptions');
    }

    /** Écarte une suggestion de détection. */
    public function ignore(): void
    {
        $keyword = mb_substr((string) input('keyword'), 0, 100);
        if ($keyword !== '') {
            db()->prepare('INSERT IGNORE INTO subscription_ignored (keyword) VALUES (?)')->execute([$keyword]);
        }
        redirect('subscriptions');
    }

    /** Accepte le nouveau montant détecté sur le relevé (hausse de prix). */
    public function updateAmount(): void
    {
        $sub = $this->find(int_input('id'));
        $amount = abs(parse_amount(input('amount')));
        if ($amount > 0) {
            db()->prepare('UPDATE subscriptions SET previous_amount = amount, amount = ?, amount_changed_at = ? WHERE id = ?')
                ->execute([$amount, date('Y-m-d'), $sub['id']]);
            flash('success', "Montant de « {$sub['name']} » mis à jour.");
        }
        redirect('subscriptions');
    }
}
