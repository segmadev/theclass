let canvas = document.getElementById("canvas");
let context = canvas.getContext("2d");

canvas.width = window.innerWidth - 30;
canvas.height = window.innerHeight - 10;

canvas.style.border = "5px solid red";

let canvas_width = canvas.width;
let canvas_height = canvas.height;
let offset_x;
let offset_y;

let get_offset = function() {
    let canvas_offsets = canvas.getBoundingClientRect();
    offset_x = canvas_offsets.left;
    offset_y = canvas_offsets.top;
}

get_offset();
window.onscroll = function() { get_offset(); }
window.onresize = function() { get_offset(); }
canvas.onresize = function() { get_offset(); }


let shapes = [];
let current_shape_index = null;
let is_dragging = false;
let startX;
let startY;

shapes.push({x:200, y:50, width: 200, height: 200, color: "black"});
shapes.push({x:10, y:50, width: 200, height: 200, color: "green"});
shapes.push({x:10, y:50, width: 200, height: 200, color: "green"});

let draw_shapes = function () {
    context.clearRect(0, 0, canvas_width, canvas_height);
    for (let shape of shapes) {
        context.fillStyle = shape.color;
        
        context.fillRect(shape.x, shape.y, shape.width, shape.height);
        // context.style.border = "1px solid yellow";
    }
    // draw_shapes();
}

let is_mouse_in_shape = function(x, y, shape) {
    let shape_left = shape.x;
    let shape_right = shape.x + shape.width;
    let shape_top = shape.y;
    let shape_bottom = shape.y + shape.height;

    // compelete the if statement later
    if(x > shape_left && x < shape_right && y > shape_top && y < shape_bottom){
        console.log("in shape");
        return true;
    }

    return false;
}

let mouse_down = function(event) {
    event.preventDefault();
    startX = parseInt(event.clientX - offset_x);
    startY = parseInt(event.clientY - offset_y);
    
    let index = 0;
    for (let shape of shapes){
        if(is_mouse_in_shape(startX, startY, shape)) {
            current_shape_index = index;
            is_dragging = true;
            return;
        }
        index++;
    }
    console.log(index);
}

let mouse_up = function(event) {
    if(!is_dragging){ 
        return;
    }

    event.preventDefault();
    is_dragging = false;
}

let mouse_out = function(event) {
    if(!is_dragging) {
       return; 
    }

    event.preventDefault();
    is_dragging = false;
}

let mouse_move = function(event) {
    if(!is_dragging) {
        return;
    } else {
        event.preventDefault();
        let mouseX = parseInt(event.clientX - offset_x);
        let mouseY = parseInt(event.clientY - offset_y);

        let dx = mouseX - startX;
        let dy = mouseY - startY;

        let current_shape = shapes[current_shape_index];
        current_shape.x += dx;
        current_shape.y += dy;

         draw_shapes();

         startX = mouseX;
         startY = mouseY;

    }
}

canvas.onmousedown = mouse_down;
canvas.onmouseup = mouse_up;
canvas.onmousemove = mouse_move;
canvas.onmouseout = mouse_out;
draw_shapes();