<?php
/*********************************************************************
    org_bill.php

    Organisation Billing

    Robin Toy <robin@strobe-it.co.uk>
    https://www.strobe-it.co.uk

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require('staff.inc.php');

// Collect Time Items
// --Determine ID value for time-type

function countTime($ticketid) {
    $ticket = Ticket::lookup($ticketid);
    if (!$ticket)
        return array();
    return $ticket->getTimeTotalsByType(false);
}

//Get Organisation Details
$org = Organization::lookup($_REQUEST['orgid']);

//Navigation & Page Info
$nav->setTabActive('users');
$ost->setPageTitle(sprintf(__('%s - Bill / Invoice'),$org->getName()));

// dates come out of the form like dd/mm/yyyy and must convert to yyyy-mm-dd for sql
$startdate = DateTime::createFromFormat('d/m/Y', $_REQUEST['startdate'])->format('Y-m-d');
$enddate = DateTime::createFromFormat('d/m/Y', $_REQUEST['enddate'])->format('Y-m-d');

//Ticket information
// --Generate SQL
$tickets = Ticket::objects()
//New code contributed by @damiangraber
    ->filter([
        'user__org_id' => $org->getId(),
        'created__range' => array($startdate, $enddate),
    ])
    ->values('staff_id', 'isoverdue', 'ticket_id', 'number',
        'cdata__subject', 'user__default_email__address', 'source',
        'status_id', 'status__name', 'status__state', 'dept_id',
        'dept__name', 'user__name', 'lastupdate', 'isanswered', 'lastupdate')
    ->order_by('-created');

$tickets->annotate(array(
    'attachment_count' => TicketThread::objects()
        ->filter(array('ticket__ticket_id' => new SqlField('ticket_id', 1)))
        ->filter(array('entries__attachments__inline' => 0))
        ->aggregate(array('count' => SqlAggregate::COUNT('entries__attachments__id'))),
    'thread_count' => TicketThread::objects()
        ->filter(array('ticket__ticket_id' => new SqlField('ticket_id', 1)))
        ->exclude(array('entries__flags__hasbit' => ThreadEntry::FLAG_HIDDEN))
        ->aggregate(array('count' => SqlAggregate::COUNT('entries__id'))),
));

$subject_field = TicketForm::getInstance()->getField('subject');

require_once(STAFFINC_DIR.'header.inc.php');
?>

<h1><?php echo __('Bill / Invoice'); ?></h1>
<b><?php echo __('Organization'); ?>:</b> <?php echo $org->getName(); ?><br />
<b><?php echo __('Billing Period'); ?>:</b> <?php echo $startdate; ?> - <?php echo $enddate; ?><br /><br />
<h2><?php echo __('Labour / Time Details'); ?></h2>
<?php if (count($tickets)) { ?>
 <table class="list" border="0" cellspacing="1" cellpadding="2" width="940">
    <thead>
        <tr>
            <?php
            if (0) {?>
            <th width="8px">&nbsp;</th>
            <?php
            } ?>
            <th width="70"><?php echo __('Ticket'); ?></th>
            <th width="100"><?php echo __('Last Update'); ?></th>
            <th width="100"><?php echo __('Status'); ?></th>
            <th width="300"><?php echo __('Subject'); ?></th>
            <th width="400"><?php echo __('Time Summary'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php
    foreach ($tickets as $T) {
        $flag=null;
        if($T['isoverdue'])
            $flag='overdue';
        $subject = $subject_field->display($subject_field->to_php($T['cdata__subject']));
        $threadcount=$T['thread_count'];
        ?>
        <tr id="<?php echo $T['ticket_id']; ?>">
            <?php
            //Implement mass  action....if need be.
            if (0) { ?>
            <td align="center" class="nohover">
                <input class="ckb" type="checkbox" name="tids[]" value="<?php echo $T['ticket_id']; ?>" <?php echo $sel?'checked="checked"':''; ?>>
            </td>
            <?php
            } ?>
            <td title="<?php echo $T['user__default_email__address']; ?>" nowrap>
              <a class="Icon <?php echo strtolower($T['source']); ?>Ticket preview"
                title="<?php echo __('Preview Ticket'); ?>"
                href="tickets.php?id=<?php echo $T['ticket_id']; ?>"
                data-preview="#tickets/<?php echo $T['ticket_id']; ?>/preview"
                ><?php echo $T['number']; ?></a></td>
            <td align="center" nowrap><?php echo Format::datetime($T['lastupdate']); ?></td>
<?php       $displaystatus=TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']);
            if(!strcasecmp($T['status__state'],'open'))
                $displaystatus="<b>$displaystatus</b>";
            echo "<td>$displaystatus</td>"; ?>
            <td><div style="max-width: <?php
                $base = 279;
                // Make room for the paperclip and some extra
                if ($T['attachment_count']) $base -= 18;
                // Assume about 8px per digit character
                if ($threadcount > 1) $base -= 20 + ((int) log($threadcount, 10) + 1) * 8;
                // Make room for overdue flag and friends
                if ($flag) $base -= 20;
                echo $base; ?>px; max-height: 1.2em"
                class="<?php if ($flag) { ?>Icon <?php echo $flag; ?>Ticket <?php } ?>link truncate"
                <?php if ($flag) { ?> title="<?php echo ucfirst($flag); ?> Ticket" <?php } ?>
                href="tickets.php?id=<?php echo $T['ticket_id']; ?>"><?php echo $subject; ?></div>
<?php               if ($T['attachment_count'])
                    echo '<i class="small icon-paperclip icon-flip-horizontal" data-toggle="tooltip" title="'
                        .$T['attachment_count'].'"></i>';
                if ($threadcount > 1) { ?>
                    <span class="pull-right faded-more"><i class="icon-comments-alt"></i>
                        <small><?php echo $threadcount; ?></small>
                    </span>
                <?php } ?>
            </td>
            <td><?php
                foreach (countTime($T['ticket_id']) as $name=>$time) {
                    echo sprintf('%s %s<br/>', Ticket::formatTime($time), $name);
                } ?>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
<?php
} else {
    echo '<p>' . __('No tickets found') . '</p>';
}

require_once(STAFFINC_DIR.'footer.inc.php');
?>