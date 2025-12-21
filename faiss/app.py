import os
import numpy as np
import faiss
from flask import Flask, request, jsonify
import json

app = Flask(__name__)

INDEX_PATH = '/data/faiss/index.idx'
DOCS_PATH = "/data/faiss/documents.json"
DIMENSION = 384

index = None
if os.path.exists(INDEX_PATH):
    index = faiss.read_index(INDEX_PATH)
else:
    base_index = faiss.IndexFlatL2(DIMENSION)
    index = faiss.IndexIDMap(base_index)

def load_docs():
    if os.path.exists(DOCS_PATH):
        with open(DOCS_PATH, "r") as f:
            return json.load(f)
    return {}

def save_docs(docs):
    with open(DOCS_PATH, "w") as f:
        json.dump(docs, f)

@app.route('/init', methods=['POST'])
def init_index():
    global index

    base_index = faiss.IndexFlatL2(DIMENSION)
    index = faiss.IndexIDMap(base_index)
    faiss.write_index(index, INDEX_PATH)

    with open(DOCS_PATH, "w") as f:
        json.dump({}, f)

    return jsonify({"status": "success"})

@app.route('/add', methods=['POST'])
def add_vectors():
    global index

    data = request.json
    ids = data.get("ids")
    vectors = data.get("vectors")
    documents = data.get("documents")

    if not vectors or not ids or not documents:
        return jsonify({"error": "Invalid payload"}), 400

    vectors_array = np.array(vectors, dtype=np.float32)
    ids_array = np.array(ids, dtype=np.int64)

    index.add_with_ids(vectors_array, ids_array)
    faiss.write_index(index, INDEX_PATH)

    docs = load_docs()
    for key, document in enumerate(documents):
        docs[str(ids[key])] = document
    save_docs(docs)

    return jsonify({"status": "success"})

@app.route('/search', methods=['POST'])
def search_vectors():
    global index

    data = request.json
    query_vectors = np.asarray(data["query_vectors"], dtype=np.float32)
    k = int(data.get("k", 5))

    if query_vectors.ndim == 1:
        query_vectors = query_vectors.reshape(1, -1)

    distances, ids = index.search(query_vectors, k)
    docs = load_docs()

    results = []
    for i in range(len(query_vectors)):
        hits = []
        for j in range(k):
            doc_id = ids[i][j]

            if doc_id == -1:
                continue

            hits.append({
                "id": int(doc_id),
                "distance": float(distances[i][j]),
                "document": docs.get(str(doc_id))
            })

        results.append(hits)

    return jsonify({"results": results})

@app.route('/delete', methods=['DELETE'])
def delete_all_vectors():
    global index

    index.reset()
    faiss.write_index(index, INDEX_PATH)

    return jsonify({"status": "success", "message": "All vectors deleted"})

@app.route('/status', methods=['GET'])
def status():
    global index

    return jsonify({
        "status": "running",
        "dimension": DIMENSION,
        "total_vectors": index.ntotal
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
