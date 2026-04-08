let canvas = document.getElementById("canvas");
let addShape = document.getElementById("addShape");
let duplicateShape = document.getElementById("duplicateShape");
let copyShape = document.getElementById("copyShape");
let removeShape = document.getElementById("removeShape");
let saveShape = document.getElementById("saveShape");
let changeColor = document.getElementById("changeColor");
let alignText = document.getElementById("alignText");
let control = document.getElementById("control");
let textSize = document.getElementById("textSize");
let changeWdith = document.getElementById("changeWdith");
let changeHeight = document.getElementById("changeHeight");
let changeBackgroundColor = document.getElementById("changeBackgroundColor");
let addstyle = document.getElementById("addstyle");
let displayMessage = document.getElementById("displayMessage");
let safeToSave = false;
// input gobal var
let inputType = document.getElementById("inputType");
let info = document.getElementById("info");
let nomin = document.getElementById("nomin");
let nomax = document.getElementById("nomax");
let amountmin = document.getElementById("amountmin");
let amountmax = document.getElementById("amountmax");
let currency = document.getElementById("currency");
let regexpattern = document.getElementById("regexpattern");
let statictext = document.getElementById("statictext");
let datepattern = document.getElementById("datepattern");

// hideAllDivs(info);
// let context = canvas.getContext("2d");

// canvas.width = window.innerWidth - 30;
// canvas.height = window.innerHeight - 10;

// canvas.style.border = "5px solid red";
let edit = [];
edit.width = canvas.clientWidth;
edit.height = canvas.clientHeight;
edit.img = "";
edit.shapes = [];
canvas.querySelector("img").src = edit.img;

let canvas_width = edit.width;
let canvas_height = edit.height;
let offset_x;
let offset_y;

let get_offset = function () {
  let canvas_offsets = canvas.getBoundingClientRect();
  offset_x = canvas_offsets.left;
  offset_y = canvas_offsets.top;
};

get_offset();
window.onscroll = function () {
  get_offset();
};

window.onresize = function () {
  get_offset();
};
canvas.onresize = function () {
  get_offset();
};

let shapes = edit.shapes;
let current_shape_index = null;
let is_dragging = false;
let startX;
let startY;
let draw_shapes = function (current_shape = null) {
  hideAllDivs(canvas);

  // var divs = canvas.querySelectorAll('div');
  // divs.forEach(function(div) {
  //     div.remove();
  //   });
  // context.clearRect(0, 0, canvas_width, canvas_height);
  for (let shape of shapes) {
    // console.log("the loopp");
    // context.fillStyle = shape.color;
    var theShape = document.createElement("div");
    // theShape.addEventListener("click", mouse_move);
    canvas.appendChild(theShape);

    var borderColor = "gray";
    if (shape.active) {
      borderColor = "green";
    }
    if (!shape.style) {
      shape.style = "";
    }
    console.log(shape.content);
    if (shape.content == false && getGet("generate")) {
      // draw_shapes();
      location.reload();
      return true;
    }
    theShape.innerHTML = shape.content;
    theShape.setAttribute(
      "style",
      "width: " +
        shape.width +
        "px;" +
        "height: " +
        shape.height +
        "px;" +
        "border: 0.5px dashed " +
        borderColor +
        ";" +
        "position: absolute;" +
        "top: " +
        shape.top +
        "px;" +
        "left: " +
        shape.left +
        "px;" +
        "color: " +
        shape.color +
        ";" +
        "text-align: " +
        shape.align +
        ";" +
        "background-color: " +
        shape.background +
        ";" +
        "font-size: " +
        shape.size +
        "px;" +
        shape.style
    );
    if (shape.active) {
      setInputValue(shape);
    }
    // theShape.style.width = shape.width+"px";
    // theShape.style.height = shape.height+"px";
    // context.fillRect(shape.left, shape.top, shape.width, shape.height);
    // context.style.border = "1px solid yellow";
  }
  return true;
  // AutosaveShape();
  // draw_shapes();
};

let is_mouse_in_shape = function (x, y, shape) {
  // let checkPosition = x - shape.left;
  let shape_left = shape.left;
  let shape_right = shape.left + shape.width;
  let shape_top = shape.top;
  let shape_bottom = shape.top + shape.height;

  // compelete the if statement later
  if (x > shape_left && x < shape_right && y > shape_top && y < shape_bottom) {
    return true;
  }

  return false;
};

let mouse_down = function (event) {
  event.preventDefault();
  startX = parseInt(event.clientX - offset_x);
  startY = parseInt(event.clientY - offset_y);

  let index = 0;
  for (let shape of shapes) {
    if (is_mouse_in_shape(startX, startY, shape)) {
      current_shape_index = index;
      is_dragging = true;
      return;
    }
    index++;
  }
  // console.log(index);
};

let mouse_up = function (event) {
  if (!is_dragging) {
    return;
  }

  event.preventDefault();
  is_dragging = false;
};

let mouse_out = function (event) {
  if (!is_dragging) {
    return;
  }

  event.preventDefault();
  is_dragging = false;
};

let mouse_move = function (event) {
  if (!is_dragging) {
    return;
  } else {
    event.preventDefault();
    let mouseX = parseInt(event.clientX - offset_x);
    let mouseY = parseInt(event.clientY - offset_y);

    let dx = mouseX - startX;
    let dy = mouseY - startY;

    let current_shape = shapes[current_shape_index];
    current_shape.left += dx;
    current_shape.top += dy;
    current_shape.active = true;
    // console.log();
    unsetActive(current_shape_index);
    draw_shapes(current_shape);

    startX = mouseX;
    startY = mouseY;
  }
};

addShape.addEventListener("click", function name(event) {
  newShape();
  draw_shapes();
});

function newShape() {
  shapes.push({
    left: 10,
    top: 40,
    width: canvas_width / 1.2,
    height: 60,
    color: "green",
    background: "transparent",
    textAlign: "center",
    border: "1px soild black",
    align: "center",
    active: true,
    size: 10,
    inputType: "date",
    nomin: 0,
    nomax: 0,
    amountmin: 0,
    amountmax: 0,
    currency: "$",
    regexpattern: "",
    regexpatternlength: 10,
    statictext: "",
    datepattern: "",
    content: "Placeholder",
    style: "",
  });
  unsetActive(shapes.length - 1);
}

function copyShapetoShape(copyShape, toShape) {
  for (let key in shapes[copyShape]) {
    if (key != "top" && key != "left") {
      shapes[toShape][key] = shapes[copyShape][key];
    }
  }
}

removeShape.addEventListener("click", function (event) {
  shapes.forEach(function (obj, index) {
    if (obj.active) {
      // console.log("yes");
      shapes.splice(index, 1);
      draw_shapes();
    }
  });
});

function duplicateShapes() {
  let = ActiveIndex = 0;
  shapes.forEach(function (obj, index) {
    if (obj.active) {
      ActiveIndex = index;
      // obj.active = false;
    }
  });
  newShape();
  copyShapetoShape(ActiveIndex, shapes.length - 1);
  draw_shapes();
  return [ActiveIndex, shapes.length - 1];
}

duplicateShape.addEventListener("click", function (event) {
  duplicateShapes();
});

copyShape.addEventListener("click", function (event) {
  copyShapes = duplicateShapes();
  shapes[copyShapes[1]]["copy"] = copyShapes[0];
  console.log(shapes);
});

saveShape.addEventListener("click", function (event) {
  // var data = {
  //     canvaInfo: toString(edit),
  // };
  // console.log(edit);
  AutosaveShape();
});

function AutosaveShape() {
  var editToString = JSON.stringify(shapes);
  // alert(edit);

  //   xhr.send(JSON.stringify(data));
  if (safeToSave) {
    // console.log(edit);
    $.ajax({
      url: "canva_passer",
      data: {
        imageUrl: edit.img,
        width: edit.width,
        height: edit.height,
        shapes: editToString,
      },
      type: "POST",
      success: function (response) {
        if (displayMessage) {
          displayMessage.innerHTML = response;
        }
        console.log(response);
      },
    });
  }
}

var inputs = control.querySelectorAll("input, select, textarea");
inputs.forEach(function (input) {
  input.addEventListener("input", function (event) {
    shapes.forEach(function (obj, index) {
      if (obj.active) {
        console.log(input.value);
        obj[input.name] = input.value;
        // console.log(obj);
        draw_shapes();
      }
    });
  });
});

inputType.addEventListener("input", function (event) {
  showInfoDiv();
});

function unsetActive(current_shape_index) {
  shapes.forEach(function (obj, index) {
    if (index === current_shape_index) {
      changeWdith.value = obj.width;
      changeHeight.value = obj.height ?? 60;
      changeColor.value = obj.color;
      alignText.value = obj.align;
      textSize.value = obj.size;
      changeBackgroundColor.value = obj.background;

      obj.active = true;
    } else {
      obj.active = false;
    }
  });
}

// hide all divs
function hideAllDivs(thediv, type = "remove") {
  var divs = thediv.querySelectorAll("div");
  divs.forEach(function (div) {
    if (type == "remove") {
      div.remove();
    } else {
      div.style.display = "none";
    }
  });
}

showInfoDiv();
function showInfoDiv() {
  hideAllDivs(info, "hide");
  // console.log("got her");
  var div = document.getElementById(inputType.value);
  div.style.display = "block";
  var divs = div.querySelectorAll("div");
  divs.forEach(function (div) {
    div.style.display = "block";
  });
}

function setNewValue(shape, key) {
  var keyValue = document.getElementById(key).value;
  shape[key] = keyValue;
}

function setInputValue(shape) {
  inputType.value = shape.inputType;
  nomin.value = shape.nomin;
  nomax.value = shape.nomax;
  datepattern.value = shape.datepattern;
  amountmin.value = shape.amountmin;
  amountmax.value = shape.amountmax;
  regexpattern.value = shape.regexpattern;
  statictext.value = shape.statictext;
  addstyle.value = shape.style;
  currency.value = shape.currency;
  showInfoDiv();
}

canvas.onmousedown = mouse_down;
canvas.onmouseup = mouse_up;
canvas.onmousemove = mouse_move;
canvas.onmouseout = mouse_out;

// fetch info from DB
getShapesInfo();
function getShapesInfo() {
  // check id GET ID isset

  let editID = getGet("id");
  // let generate = ;

  if (editID) {
    canvas.querySelector("img").style.display = "none";
    canvas.innerHTML += "<div>please wait...</div>";
    $.ajax({
      url: "canva_passer",
      data: {
        getEdit: editID,
      },
      type: "POST",
      success: function (response) {
        canvas.querySelector("img").style.display = "block";
        if (response != "") {
          var data = JSON.parse(response);
          if (!data) {
            return false;
          }
          edit.img = data.imageUrl;
          if (data.width == "") {
            data.width = canvas.clientWidth;
          }
          if (data.height == "") {
            data.height = canvas.clientHeight;
          }
          edit.width = data.width ?? canvas.clientWidth;
          edit.height = data.height ?? canvas.clientHeight;
          canvas.style.width = edit.width + "px";
          canvas.style.height = edit.height + "px";
          canvas.querySelector("img").src = edit.img;
          canvas.querySelector("img").style.width = edit.width + "px";
          canvas.querySelector("img").style.height = edit.height + "px";
          edit.shapes = JSON.parse(data.shapes);
          shapes = JSON.parse(data.shapes);
          // console.log(edit);
          // var data = response);
          let draw = draw_shapes();
          if (getGet("generate") && draw) {
            // console.log("downloaded");
            setTimeout(downloaddelayed, 3000);
            // $("#download").click();
          }
        }
        let draw = draw_shapes();
      },
    });
  }
  safeToSave = true;
  // pass to php
  // get respose
  // convert response to Json
  // get URL image into edit.img
  // get shapes into edit.shapes
  // redraw shapes
}

function downloaddelayed() {
  $("#download").click();
}

function getGet(key) {
  // Get the current URL
  var url = new URL(window.location.href);

  // Get the value of the "id" parameter
  var idParam = url.searchParams.get(key);
  // console.log("idParam");
  // Check if the "id" parameter is set
  if (idParam) {
    return idParam;
  } else {
    return false;
  }
}

// convert canva to image

// function autoClick(){
//     $("#download").click();
//   }

$(document).ready(function () {
  var element = $("#canvas");
  $("#download").on("click", function () {
    html2canvas(element, {
      onrendered: function (canvas) {
        var imageData = canvas.toDataURL("image/png", 0.5);
        var newData = imageData.replace(
          /^data:image\/png/,
          "data:application/octet-stream"
        );
        doCapture(imageData);
        //   $("#download").attr("download", "image.jpg").attr("href", newData);
      },
    });
  });
});

function doCapture(imageUrl) {
  // Create an AJAX object
  var ajax = new XMLHttpRequest();

  // Setting method, server file name, and asynchronous
  ajax.open("POST", "canva_passer", true);

  // Setting headers for POST method
  ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

  // Sending image data to server
  ajax.send("image=" + imageUrl);

  // Receiving response from server
  // This function will be called multiple times
  ajax.onreadystatechange = function () {
    // Check when the requested is completed
    if (this.readyState == 4 && this.status == 200) {
      // Displaying response from server
      console.log(this.responseText);
      // genare a new message again
      window.location.href = "canva?generate=";
    }
  };
}
// setInterval(AutosaveShape, 10000);
