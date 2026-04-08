var RandExp = require('randexp');
var text = new RandExp(/hello+ (world|to you)/).gen();
console.log(text);