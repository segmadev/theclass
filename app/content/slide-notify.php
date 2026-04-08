<?php
$script[] = 'dashboard3';
$allrecent_trades = $d->getall("trades", "status = ? ORDER BY trade_time DESC LIMIT 5", ["closed"], fetch: "moredetails");
?>
<style>
    /* div.owl-item {
        width: 500px!important;
    } */
</style>
<div class="col-xl-12 col-12 d-flex align-items-strech mb-2">
    <div class="p-2 shadow-sm w-100">
        <div class="p-1">
            <small class='text-title'>Recent Trades taken by our AI <i class="ti ti-asterisk text-primary"></i>.</small></h5>
        </div>
        <div class="">
            <div class="owl-carousel collectibles-carousel owl-theme">
                <?php if ($allrecent_trades->rowCount() > 0) {
                    foreach ($allrecent_trades as $row) {
                        $coin_full_name = $row['coinname'];
                        $coinname = str_replace("USDT", "", $row['coinname']);
                        $candles = json_decode($row['trade_candles']);
                        $open = number_format($candles[0][2], 3);
                        $close = number_format($candles[count($candles) - 1][4], 3);
                        $second = "sell";
                        if ($row['trade_type'] == 'sell') {

                            $second = "buy";
                        }
                ?>
                        <div class="item p-1 w-100">
                            <div class="d-flex bg-gray">
                                <!-- <b class="p-2 bg-primary text-light">New Trade:</b> -->
                                <div class="d-flex middle ms-2">
                                    <img style="width: 30px; height: 30px" src="https://assets.coincap.io/assets/icons/<?= strtolower($coinname) ?>@2x.png" alt="">&nbsp; <b><?= $coin_full_name ?></b>
                                </div>
                                <div class="ms-2 p-2 bg-gray d-flex middle <?= $textclass ?>"><?= $c->arrow_percentage($row['percentage'], "") . " " . $d->money_format($row['intrest_amount'], currency) ?>&nbsp; &nbsp;
                                    <span class='fs-3'>Amount: <?= $d->money_format($row['amount'], currency) ?></span>
                                </div>
                            </div>
                        </div>
                <?php }
                } ?>
            </div>
        </div>
    </div>
</div>