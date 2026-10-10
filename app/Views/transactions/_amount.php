<?php $sign = ['income' => '+', 'expense' => '−', 'transfer' => ''][$t['type']]; ?>
<span class="amount amount-<?= $t['type'] ?>"><?= $sign ?> <?= money($t['amount']) ?></span>
