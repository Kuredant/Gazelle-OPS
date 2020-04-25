<?php

if (!check_perms('admin_manage_payments')) {
    error(403);
}

$DB->prepared_query("
    SELECT ID, Text, Expiry, AnnualRent, cc, Active
    FROM payment_reminders");

$Reminders = $DB->has_results() ? $DB->to_array('ID', MYSQLI_ASSOC) : [];
$XBT = new \Gazelle\Manager\XBT($DB, $Cache);

View::show_header('Payment Dates');
?>
<div class="header">
    <h2>Payment Dates</h2>
</div>
<table>
    <tr class="colhead">
        <td>Payment</td>
        <td>Expiry</td>
        <td>Annual Rent</td>
        <td>Currency Code</td>
        <td>Equivalent XBT</td>
        <td>Active</td>
        <td>Submit</td>
    </tr>
<?php
$Row = 'b';
$totalRent = 0;

foreach ($Reminders as $r) {
    list($ID, $Text, $Expiry, $Rent, $CC, $Active) = array_values($r);
    if ($CC == 'XBT') {
        $fiatRate = 1.0;
        $Rent = sprintf('%0.6f', $Rent);
        $btcRent = sprintf('%0.6f', $Rent);
    } else {
        $fiatRate = $XBT->fetchRate($CC);
        if (!$fiatRate) {
            error(0, "$ID code $CC");
        }
        $Rent = sprintf('%0.2f', $Rent);
        $btcRent = sprintf('%0.6f', $Rent / $fiatRate);
    }
    if ($Active) {
        $totalRent += $btcRent;
    }
    $Row = $Row === 'a' ? 'b' : 'a';
?>
    <tr class="row<?=$Row?>">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="action" value="payment_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <input type="text" name="text" value="<?=$Text?>" />
            </td>
            <td>
                <input type="text" name="expiry" value="<?=date('Y-m-d', strtotime($Expiry))?>" placeholder="YYYY-MM-DD" />
            </td>
            <td>
                <input type="text" name="rent" value="<?= $Rent ?>" />
            </td>
            <td>
                <select name="cc">
                    <option value="XBT"<?= $CC == 'XBT' ? ' selected="selected"' : '' ?>>XBT</option>
                    <option value="EUR"<?= $CC == 'EUR' ? ' selected="selected"' : '' ?>>EUR</option>
                    <option value="USD"<?= $CC == 'USD' ? ' selected="selected"' : '' ?>>USD</option>
                </select>
            </td>
            <td title="Based on a rate of <?= sprintf('%0.4f', $fiatRate)?>"><?= $btcRent ?></td>
            <td>
                <input type="checkbox" name="active"<?=($Active == '1') ? ' checked="checked"' : ''?> />
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this payment? This is an irreversible action!')" />
            </td>
        </form>
    </tr>
<?php } ?>
    <tr class="colhead">
        <td colspan="4">Create Payment</td>
    </tr>
    <tr class="rowa">
        <form class="manage_form" name="accounts" action="" method="post">
            <input type="hidden" name="action" value="payment_alter" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <input type="text" size="15" name="text" value="" />
            </td>
            <td>
                <input type="text" size="10" name="expiry" value="" placeholder="YYYY-MM-DD" />
            </td>
            <td>
                <input type="text" name="rent" value="0" />
            </td>
            <td>
                <select name="cc">
                    <option value="EUR" selected="selected">EUR</option>
                    <option value="USD">USD</option>
                    <option value="XBT">XBT</option>
                </select>
            </td>
            <td>&nbsp;</td>
            <td>
                <input type="checkbox" name="active" checked="checked" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
</table>

<div class="box pad">
<div class="header">
    <h2>Budget Forecast</h2>
</div>
    <table>
        <tr class="colhead">
            <td>&nbsp;</td>
            <td>Monthly</td>
            <td>Quarterly</td>
            <td>Annual</td>
        </tr>
        <tr>
            <td>Budget</td>
            <td><?= sprintf('%0.4f', $totalRent / 12) ?></td>
            <td><?= sprintf('%0.4f', $totalRent / 3) ?></td>
            <td><?= sprintf('%0.4f', $totalRent) ?></td>
        </tr>
        <tr>
            <td>Actual</td>
            <td><?= sprintf('%0.4f', Donations::donations_total_month( 1)) ?></td>
            <td><?= sprintf('%0.4f', Donations::donations_total_month( 4)) ?></td>
            <td><?= sprintf('%0.4f', Donations::donations_total_month(12)) ?></td>
        </tr>
        <tr>
            <td>Target</td>
            <td><?= sprintf('%0.1f%%', Donations::donations_total_month( 1) / ($totalRent/12) * 100) ?></td>
            <td><?= sprintf('%0.1f%%', Donations::donations_total_month( 4) / ($totalRent/ 4) * 100) ?></td>
            <td><?= sprintf('%0.1f%%', Donations::donations_total_month(12) / ($totalRent   ) * 100) ?></td>
        </tr>
    </table>
</div>

<?php

View::show_footer();