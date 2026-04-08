const screenshot = document.getElementById("screenshot");
var imageUrl = "../assets/images/screenshot/coinbase.jpg";
const imageP = getImgSize("../assets/images/screenshot/coinbase.jpg");
// btns
const addLabel = document.querySelector("#addInfo");
const pickPosition = document.querySelector("#pickPosition");
var labels = document.querySelector("#labels");

// set div width and height to image size
// screenshot.setAttribute(
//   "style",
//   "width: " +
//     imageP.width +
//     "px;"
// );

// pick a position
pickPosition.addEventListener("click", function () {
  document.getElementById("info").style.display = "block";
  screenshot.addEventListener("mousemove", function (event) {
    getCursor(event);
  });
});

// add new label
addLabel.addEventListener("click", function () {
  var message = document.querySelector("#label_form #message");
  message.innerHTML = "Please wait...";
  var labelLis = document.querySelectorAll("#labels label");
  // get labelName
  // check if exits
  // placename on for label and ID if the radio input
  var labelInput = document.getElementById("labelName");
  var labelName = labelInput.value;
  if(labelName == "") {
    message.innerHTML = "<b class='text-danger'>Enter label name.</b>";
    return false;
  }


  try {
    array.forEach((ele) => {
       if (condition) {
          throw new Error("Break the loop.")
       }
    })
 } catch (error) {

 }

 
 var error = false;
  labelLis.forEach(function (label) {
    if(label.getAttribute("for") == labelName) {
         message.innerHTML = "<b class='text-danger'>You can't use this label name</b>";
         error = true;
        throw new Error("Break the loop.");
    }
  });
  if(!error){
    const placeholder = document.getElementById("placeholder");
    // placeholder.innerHTML.replace("#replaceWithDivName", "#"+labelName);
    document.querySelector("#placeholder #btn").innerHTML = labelName;
    document.querySelector("#placeholder #removeLabel").setAttribute("onclick", "removeDIv('"+labelName+"')");
    // document.querySelector("#placeholder label input[type='radio']").id = labelName;
    document.querySelector("#placeholder label").setAttribute("for", labelName);
    document.querySelector("#placeholder label").setAttribute("id", labelName);
    // document.querySelector("#placeholder label input[type='radio']").setAttribute("name", labelName);
    labels.innerHTML += placeholder.innerHTML;
    labelInput.value = "";
    message.innerHTML = "<b class='text-success'>Added</b>"
    listEvent();
    document.querySelector("#placeholder label").setAttribute("id", "");
  }
});


screenshot.addEventListener("click", function (event) {
  let x = event.clientX;
  let y = event.clientY;
  const activeLabel = document.querySelector("#labels .active");
  if (!activeLabel) {
    alert("Select a label");
    return;
  }
  activeLabel.querySelector("#positionX").value = x;
  activeLabel.querySelector("#positionY").value = y;
  document.getElementById("info").style.display = "none";

  const TextPlaceholder = document.createElement('p');
  document.getElementById("screenshot").appendChild(TextPlaceholder);
  TextPlaceholder.id = "Text-1";
  TextPlaceholder.innerHTML = "Placeholder";
  TextPlaceholder.style.position = "absolute";
  TextPlaceholder.style.top = y + "px";
  TextPlaceholder.style.left = x + "px";

});

function getImgSize(imgSrc) {
  var newImg = new Image();
  newImg.src = imgSrc;
  var height = newImg.height;
  var width = newImg.offsetWidth;
  p = $(newImg).ready(function () {
    return { width: newImg.width, height: newImg.height };
  });
  return { width: p[0]["width"], height: p[0]["height"] };
}

function getCursor(event) {
  let x = event.clientX;
  let y = event.clientY;
  let _position = `X: ${x}<br>Y: ${y}`;
  const infoElement = document.getElementById("info");
  screenshot.style.cursor = "crosshair";
  infoElement.innerHTML = "<b class='fs-1'>Select Position</b><br>";
  infoElement.innerHTML += _position;
  infoElement.style.top = y + "px";
  infoElement.style.left = x + 20 + "px";
 
}



function listEvent() {
  var labels = document.querySelectorAll("#labels label");
  labels.forEach(function (label) {
    label.addEventListener("click", function () {
      // Remove the "active" class from all labels
      labels.forEach(function (label) {
        label.classList.remove("active");
      });
      // Add the "active" class to the clicked label
      this.classList.add("active");
    });
  });
}
listEvent();

function removeDIv(queryName) {
  document.getElementById(queryName).remove();
}