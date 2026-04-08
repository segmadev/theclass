<?php
$script[] = 'dashboard3';
$u->generate_bot_withdraw();
// $allrecent_withdraw = $d->getall("users", "acct_type = ? ORDER BY RAND() LIMIT ".rand(10, 20), ["bot"], fetch: "moredetails");
$allrecent_withdraw = $d->getall("withdraw", "status = ? and date <= ? ORDER BY date desc LIMIT ".rand(10, 20), ["bot", date("Y-m-d H:i:s")], fetch: "moredetails");
?>
<style>
    /* div.owl-item {
        width: 500px!important;
    } */
</style>
<div class="col-xl-12 col-12 d-flex align-items-strech mb-2">
    <div class="p-2 shadow-sm w-100">
        <div class="p-1">
            <small class='text-title'>Recent Withdrawal by Investors.</small></h5>
        </div>
        <div class="">
            <div class="owl-carousel counter-carousel-withdraw owl-theme">
                <?php if ($allrecent_withdraw->rowCount() > 0) {
                    foreach ($allrecent_withdraw as $row) {
                        $amount = $row['amount'];
                ?>
                        <a id="withdraw<?= $row['ID'] ?>" data-url="modal?p=withdraw&action=view&id=<?= $row['ID'] ?>" data-title="Withdraw Details" onclick="modalcontent(this.id)" data-bs-toggle="modal" data-bs-target="#bs-example-modal-md"  class="item p-0 w-100 bg-light-dark p-2 card m-0" href='#'>
                            <div class="d-flex bg-gray">
                                <!-- <b class="p-2 bg-primary text-light">New Trade:</b> -->
                                <div class="d-flex middle ms-2">
                                <img style="width: 30px; height: 30px" class="rounded-circle" src="<?= $u->get_profile_icon_link($row['userID']) ?>" alt=""> &nbsp;&nbsp; <b><?= $u->get_name($row['userID']) ?></b>
                                <?php // $u->short_user_table($row['userID'], "index?p=investment&action=trades&userID=".$row['userID']); ?>
                                </div>
                                <div class="ms-2 p-2 bg-gray d-flex middle">
                                    <span class='fs-3'> <?php echo $d->money_format($amount, currency) ?></span>
                                </div>
                            </div>
                        </a>
                <?php }
                } ?>
            </div>
        </div>
    </div>
</div>