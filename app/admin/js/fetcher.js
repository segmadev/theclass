var loaddata = document.querySelectorAll("[data-load]");
for (var i in loaddata) {
  if (loaddata.hasOwnProperty(i)) {
    what = loaddata[i].getAttribute("data-load");
    page = loaddata[i].getAttribute("data-page");
    displayId = loaddata[i].getAttribute("data-displayId");
    path = loaddata[i].getAttribute("data-path") ?? "passer";
    limit = loaddata[i].getAttribute("data-limit") ?? 10;
    start = loaddata[i].getAttribute("data-start") ?? 0;
    isReplace = loaddata[i].getAttribute("data-isreplace") ?? 'false';
    fetchData(what, displayId, page, limit, start, path, isReplace);
  }
}

function fetchData(what, displayId, page, limit = 10, start = 0, path = "passer", isReplace = 'false') {
  data = { page: page, what: what, start: start, limit: limit};
  request = $.ajax({
    type: "POST",
    url: path,
    data: data,
  });

  request.done(function (response) {
    if (response == null || response == "null" || response == "  " || response.replace(/ /g, '') == "", response.replace(/ /g, '') == " ") {
      start = 0;
        return null;
    }
    if (checkJSON(response)) {
      obj = JSON.parse(response);
      if(obj['status'] != "ok") return null;
      response = obj['data'];
    }
    (isReplace == "false") ? document.getElementById(displayId).innerHTML += response : document.getElementById(displayId).innerHTML = response;
    display = document.getElementById(displayId);
    let elements = display.querySelectorAll("#foo");
    $i = 0;
    // console.log(elements.length);
    if (typeof iniForm === "function") {
      elements.forEach((element) => {
        iniForm(element);
        $i++;
      });
    }
    start =  parseInt(start) + parseInt(limit);
    fetchData(what, displayId, page, limit, start, path, isReplace);
  });

  // request.done(function (response) {
  //   if (response == null || response == "null" || response == "") {
  //       start = 0;
  //       return null;
  //   }
  //   let placeID = Math.floor(Math.random() * 50);
  //   display = document.getElementById(displayId);
  //   display.innerHTML += response;
  //   let elements = display.querySelectorAll("#foo");
  //   $i = 0;
  //   if (typeof iniForm === "function") {
  //     elements.forEach((element) => {
  //       iniForm(element);
  //       $i++;
  //     });
  //   }
  //   start = start + limit;
  //   fetchData(what, displayId, page, limit, start, path);
  // });
}

function get_user_info(userID) {
    if(!document.getElementById(userID)) { return null; }
    data = document.getElementById(userID);
    if(data.innerHTML != "" || data.innerHTML != "loading...") { return null;}
    request = $.ajax({
        type: "POST",
        url: "passer",
        data: {userdetails: userID},
      });
      request.done(function (response) {
        data.innerHTML = response;
      });
}

function display_content(data) {
    document.querySelectorAll('.chat-list').forEach(function(el) {
        el.style.visibility = 'hidden';
        el.style.display = 'none';
     });
   var id = $(data).data('user-id');
   if(!document.getElementById("content"+id)) {
    fetchUserData("displayDetails", id);
}else{
    document.getElementById("content"+id).style.visibility = "visible";
    document.getElementById("content"+id).style.display = "block";
}


}

function fetchUserData(displayId, id) {
    request = $.ajax({
        type: "POST",
        url: "passer",
        data: { page: "userdetails", what: "userdetails", ID: id, start: 0 },
      });
      request.done(function (response) {
        if (response == null || response == "null" || response == "") {
            start = 0;
            return null;
        }
        document.getElementById(displayId).innerHTML += response;
        document.getElementById("content"+id).style.visibility = "visible";
        document.getElementById("content"+id).style.display = "block";
      });
}

function checkJSON(text) {
  if (typeof text !== "string") {
      return false;
  }
  try {
      JSON.parse(text);
      return true;
  } catch (error) {
      return false;
  }
}
