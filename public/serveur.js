import express from "express";
import { createServer } from "http";
import { Server } from "socket.io";

const app = express();
const httpServer = createServer(app);

// Instancie Socket.IO
const io = new Server(httpServer, {
  cors: {
    origin: "*", // à adapter selon ton front
  },
});

io.on("connection", (socket) => {
  console.log("Nouvelle connexion:", socket.id);

  socket.on("message", (msg) => {
    console.log("Message reçu:", msg);
    socket.emit("message", `Reçu: ${msg}`);
  });

  socket.on("disconnect", () => {
    console.log("Déconnexion:", socket.id);
  });
});

// Lance le serveur HTTP
const PORT = 8001;
httpServer.listen(PORT, () => {
  console.log(`Serveur Socket.IO lancé sur http://localhost:${PORT}`);
});
