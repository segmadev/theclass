
<script src="https://cdn.tiny.cloud/1/<?= $d->get_settings("tiny_API") ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: '#richtext',
        height: 400,
        plugins: 'media link',
      });
      tinymce.init({
        selector: '#richtext2',
        height: 400
      });
      tinymce.init({
        selector: '#richtext_help',
        plugins: 'media link',
        // toolbar: 'media',
        toolbar: 'media link code undo redo styles bold italic alignleft aligncenter alignright alignjustify | bullist numlist outdent indent',
        height: 400
      });
    </script>

<style>
    .tox-notifications-container {
        display: none!important;
    }
</style>