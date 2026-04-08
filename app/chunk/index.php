<!DOCTYPE html>
<html>
  <head>
    <title>Chunking Upload Demo</title>
    <link rel="stylesheet" href="x-dummy.css">
  </head>
  <body>
    <!-- (A) UPLOAD BUTTON & FILE LIST -->
    <form>
      <div id="list"></div>
      <input type="button" id="pick" value="Upload">
      <input type="button" value="Upload file" id="uploadFile">
    </form>

    <!-- (B) LOAD PLUPLOAD FROM CDN -->
    <!-- https://cdnjs.com/libraries/plupload -->
    <!-- https://www.plupload.com/ -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/plupload/3.1.5/plupload.full.min.js"></script>
    <script>
        
    // (C) INITIALIZE UPLOADER
    window.onload = () => {
        var list = document.getElementById("list");

// (C2) INIT PLUPLOAD
var uploader = new plupload.Uploader({
  runtimes: "html5",
  browse_button: "pick",
  url: "../chunk",
  chunk_size: "10mb",
  multi_selection: false,
  filters: {
  mime_types : [
    { title : "Videos", extensions : "mp4,mov" },
  ]
},
  init: {
    PostInit: (e) => list.innerHTML = "",
    FilesAdded: (up, files) => 
    {
     
      plupload.each(files, file => {
        console.log(file);
        let row = document.createElement("div");
        row.id = file.id;
        row.innerHTML = `${file.name} (${plupload.formatSize(file.size)}) <strong></strong>`;
        // list.innerHTML = "";
        list.appendChild(row);
      });
    //   uploader.start();
    },
    UploadProgress: (up, file) => {
      document.querySelector(`#${file.id} strong`).innerHTML = `${file.percent}%`;
    },
    Error: (up, err) => console.error(err)
  }
});
uploader.init();
document.getElementById("uploadFile").addEventListener("click", function(e) {
    uploader.start();
    });
};



   

    </script>
  </body>
</html>