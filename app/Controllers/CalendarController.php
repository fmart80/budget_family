<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Finance;
use App\Forecast;
use App\Subscriptions;

/** Calendrier des prélèvements et rentrées d'argent d'un mois. */
final class CalendarController
{
    /** @return list<array{date: string, name: string, amount: float, type: string, kind: string, status?: string, url?: string}> */
    public static function events(string $month): array
    {
        [$start, $end] = Finance::monthRange($month);
        $daysInMonth = (int) date('t', strtotime($start));
        $dayDate = fn (int $day) => $month . '-' . str_pad((string) min($day, $daysInMonth), 2, '0', STR_PAD_LEFT);
        $events = [];

        // Factures (dates exactes).
        $stmt = db()->prepare('SELECT * FROM bills WHERE due_date BETWEEN ? AND ?');
        $stmt->execute([$start, $end]);
        $billNames = [];
        foreach ($stmt as $b) {
            $billNames[mb_strtolower($b['name'])] = true;
            $events[] = ['date' => $b['due_date'], 'name' => $b['name'], 'amount' => (float) $b['amount'], 'type' => 'expense',
                'kind' => 'bill', 'status' => $b['status'], 'url' => url($b['status'] === 'due' ? 'bills/pay' : 'bills', ['id' => $b['id']])];
        }

        // Abonnements actifs : chaque prélèvement tombant dans le mois.
        foreach (Subscriptions::all('active') as $s) {
            $step = Subscriptions::CYCLES[$s['cycle']] ?? 1;
            for ($i = 0, $date = $s['anchor_date']; $date <= $end && $i < 1000; $date = BillsController::addMonths($s['anchor_date'], $step * ++$i)) {
                if ($date >= $start) {
                    $events[] = ['date' => $date, 'name' => $s['name'], 'amount' => $s['amount'], 'type' => 'expense',
                        'kind' => 'subscription', 'url' => url('subscriptions/edit', ['id' => $s['id']])];
                }
            }
        }

        // Postes prévus (sans doublon avec une facture du même nom ce mois-ci).
        foreach (Forecast::items() as $item) {
            if ($item['source'] !== 'planned' || !Forecast::occursIn($item, $month) || isset($billNames[mb_strtolower($item['name'])])) {
                continue;
            }
            $events[] = ['date' => $dayDate((int) $item['day']), 'name' => $item['name'], 'amount' => $item['amount'],
                'type' => $item['type'], 'kind' => 'planned', 'url' => $item['edit_url']];
        }

        // Mensualités de dettes.
        foreach (Forecast::debtSchedules() as $d) {
            if (isset($d['schedule'][$month])) {
                $events[] = ['date' => $dayDate($d['day']), 'name' => $d['name'], 'amount' => $d['schedule'][$month],
                    'type' => 'expense', 'kind' => 'debt', 'url' => url('debts')];
            }
        }

        usort($events, fn ($a, $b) => [$a['date'], $a['type'] === 'income' ? 0 : 1] <=> [$b['date'], $b['type'] === 'income' ? 0 : 1]);
        return $events;
    }

    public function index(): void
    {
        $month = valid_month((string) input('month'));
        $events = self::events($month);
        $byDay = [];
        foreach ($events as $e) {
            $byDay[$e['date']][] = $e;
        }

        // Solde indicatif jour par jour, pour le mois en cours et les mois futurs.
        $current = date('Y-m');
        $startBalance = null;
        if ($month === $current) {
            $balances = Finance::balances();
            $startBalance = array_sum(array_map(fn ($id) => $balances[$id] ?? 0, Forecast::defaultAccountIds()));
        } elseif ($month > $current) {
            $diff = (int) ((strtotime($month . '-01') - strtotime($current . '-01')) / 86400 / 28);
            $forecast = Forecast::build(min(24, $diff + 2));
            foreach ($forecast['months'] as $i => $m) {
                if ($m['month'] === $month) {
                    $startBalance = $i > 0 ? $forecast['months'][$i - 1]['balance'] : $forecast['start_balance'];
                }
            }
        }

        $income = array_sum(array_map(fn ($e) => $e['type'] === 'income' ? $e['amount'] : 0, $events));
        $expense = array_sum(array_map(fn ($e) => $e['type'] === 'expense' ? $e['amount'] : 0, $events));
        view('calendar/index', [
            'title' => 'Calendrier', 'month' => $month, 'byDay' => $byDay, 'events' => $events,
            'income' => $income, 'expense' => $expense, 'startBalance' => $startBalance,
        ]);
    }
}
