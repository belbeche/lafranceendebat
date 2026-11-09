require =  './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import { io } from "socket.io-client";

const socket = io("http://127.0.0.1:8001");

socket.on("connect", () => {
  console.log("Connecté avec l'ID:", socket.id);
});

socket.on("message", (msg) => {
  console.log("Message du serveur:", msg);
});


console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
