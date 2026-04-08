<?php if (in_array("modal", $script)) { 
    require_once "../content/modal.php";
} ?>
<!-- Dashboard3 -->
<?php if (in_array("scrape", $script)) { ?>
  <script src="../dist/js/scrape.js?n=11"></script>
<?php } ?>
<?php if (in_array("roles", $script)) { ?>
  <script src="js/roles.js?m=10"></script>
<?php } ?>
<?php if (in_array("dashboard3", $script)) { ?>
    <script src="../dist/libs/owl.carousel/dist/owl.carousel.min.js"></script>
  <script src="../dist/js/dashboard3.js?n=11"></script>
<?php } ?>
<?php if (in_array("account", $script)) { ?>
  <script src="js/account.js?n=<?= time(); ?>"></script>
<?php } ?>

<?php if (in_array("wizard", $script)) { ?>
    <script src="../dist/libs/jquery-steps/build/jquery.steps.min.js?n=10"></script>
    <script src="../dist/libs/jquery-validation/dist/jquery.validate.min.js"></script>
    <script src="../dist/js/forms/form-wizard.js?n=1"></script>
<?php } ?>
<?php if (in_array("qrcode", $script)) { ?>
    <script src="../dist/js/qrcode.js?n=1"></script>
<?php } ?>
<?php if (in_array("table", $script)) { ?>
    <script src="../dist/libs/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="../dist/js/datatable/datatable-basic.init.js"></script>
<?php } ?>
<?php if (in_array("fetcher", $script)) { ?>
    <script src="../dist/js/fetcher.js?n=<?= time() ?>"></script>
<?php } ?>
<?php if(in_array('chat', $script)){ ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/plupload/3.1.5/plupload.full.min.js"></script>
    <script src="../dist/js/apps/video.js?n=7887"></script>
    <script src="../dist/js/apps/chat.js?n=6373"></script>
<?php } ?>
<?php if (in_array("select2", $script)) { ?>
    <script src="../dist/libs/select2/dist/js/select2.full.min.js"></script>
    <script src="../dist/libs/select2/dist/js/select2.min.js"></script>
    <script src="../dist/js/forms/select2.init.js"></script>
<?php } ?>
<?php if (in_array("sweetalert", $script)) { ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootbox.js/5.5.2/bootbox.min.js"></script>
    <!-- <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script> -->
<?php } ?>
<?php if (in_array("textarea", $script)) { ?>
   
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#richtext',
        height: 400
      });
      tinymce.init({
        selector: '#richtext2',
        height: 400
      });
    </script>

<style>
    .tox-notifications-container {
        display: none!important;
    }
</style>
<?php } ?>



<!--  chart -->
<?php if (in_array("chart", $script)) { 
    ?>
    <script src="../dist/libs/owl.carousel/dist/owl.carousel.min.js"></script>
    <script src="../dist/libs/apexcharts/dist/apexcharts.min.js"></script>
    <script src="../dist/js/charts.js?n=5"></script>
    <?php
    if (isset($thispage)) {
        if (file_exists("pages/$thispage/chart.php")) {
            require_once "pages/$thispage/chart.php";
        }
    }
    ?>

<?php } ?>