<?php
  function generateSimpleToken(){
    return bin2hex(random_bytes(16));
  }
?>