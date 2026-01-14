# TP

## Sujet

À partir du projet au format .zip donné, réalisez une étude comparative des performances des différents systèmes de base de données vectorielle suivants :

- Chroma
- Qdrant
- Faiss

Vous devez transformer un PDF en embeddings ainsi que les stocker dans les différents systèmes de base de données vectorielle en notant les performances obtenues.
Vous devez ensuite questionner ces différents systèmes à partir d'une requête et comparer à nouveau les performances obtenues.

Pour cela, il vous est demandé de:

- Compléter les commentaires dans l'existant
- Créer des clients afin de communiquer avec les différents services

Chaque service possède une documentation détaillée sur le fonctionnement de son API (FastAPI, Chroma, Qdrant, Ollama)

Le choix de la techno à utiliser pour ce TP est libre (La correction proposée sera réalisée avec une application console Symfony)

En bonus, vous pourrez également formuler une réponse claire à la requête initiale via l'architecture Ollama fournie.

Une todo liste des tâches à réaliser est présent dans le fichier `TODO.md`

## Contenu du fichier ZIP

- Service d'embedding (FastAPI)
- API pour FAISS (FastAPI)
- Service Ollama
- Fichier compose.yaml avec les différents services conteneurisés

## Lifecycle du projet


### Lancer le projet

```bash
docker compose up
```

### Arrêter le projet

```bash
docker compose down
```

