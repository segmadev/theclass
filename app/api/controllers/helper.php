<?php 
    class ApiHelper {
        function plshelp(){
            return utilities::apiMessage("Please help me", 200, ["help"=> "I am a helper function"]);
        }
    }