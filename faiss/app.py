import os
import json
import threading
import numpy as np
import faiss
from flask import Flask, request, jsonify

# ========================
# Configuration
# ========================

INDEX_PATH = "/data/faiss/index.idx"
DOCS_PATH = "/data/faiss/documents.json"
DIMENSION = 384

# ========================
# FAISS setup
# ========================

faiss.omp_set_num_threads(os.cpu_count())

if os.path.exists(INDEX_PATH):
    index = faiss.read_index(INDEX_PATH)
else:
    base_index = faiss.IndexFlatL2(DIMENSION)
    index = faiss.IndexIDMap(base_index)

app = Flask(__name__)

# ========================
# Init Documents
# ========================

if os.path.exists(DOCS_PATH):
    with open(DOCS_PATH, "r") as f:
        docs = json.load(f)
else:
    docs = {}

docs_lock = threading.Lock()

# ========================
# Persist functions
# ========================

def persist_index_async():
    faiss.write_index(index, INDEX_PATH)

def persist_docs_async():
    with docs_lock:
        with open(DOCS_PATH, "w") as f:
            json.dump(docs, f)

# -------------------------
# Init
# -------------------------

@app.route("/init", methods=["POST"])
def init_index():
    global index, docs

    base_index = faiss.IndexFlatL2(DIMENSION)
    index = faiss.IndexIDMap(base_index)

    docs = {}

    faiss.write_index(index, INDEX_PATH)
    with open(DOCS_PATH, "w") as f:
        json.dump({}, f)

    return jsonify({"status": "success"})

# -------------------------
# Add vectors
# -------------------------

@app.route("/add", methods=["POST"])
def add_vectors():
    data = request.json

    ids = data.get("ids")
    vectors = data.get("vectors")
    documents = data.get("documents")

    if not ids or not vectors or not documents:
        return jsonify({"error": "Invalid payload"}), 400

    vectors_array = np.asarray(vectors, dtype=np.float32)
    ids_array = np.asarray(ids, dtype=np.int64)

    index.add_with_ids(vectors_array, ids_array)

    with docs_lock:
        for i, doc_id in enumerate(ids_array):
            docs[str(int(doc_id))] = documents[i]

    threading.Thread(target=persist_index_async, daemon=True).start()
    threading.Thread(target=persist_docs_async, daemon=True).start()

    return jsonify({"status": "success", "total_vectors": index.ntotal})

# -------------------------
# Search
# -------------------------

@app.route("/search", methods=["POST"])
def search_vectors():
    data = request.json
    limit = int(data.get("limit", 5))

    query_vectors = np.asarray(
        data["query_vectors"],
        dtype=np.float32
    )

    if query_vectors.ndim == 1:
        query_vectors = query_vectors.reshape(1, -1)

    distances, ids = index.search(query_vectors, limit)

    docs_local = docs

    results = []
    for ids_row, dist_row in zip(ids, distances):
        hits = [
            {
                "id": int(doc_id),
                "distance": float(dist),
                "document": docs_local.get(str(doc_id))
            }
            for doc_id, dist in zip(ids_row, dist_row)
            if doc_id != -1
        ]
        results.append(hits)

    return jsonify({"results": results})

# -------------------------
# Delete all
# -------------------------

@app.route("/delete", methods=["DELETE"])
def delete_all_vectors():
    index.reset()

    with docs_lock:
        docs.clear()

    faiss.write_index(index, INDEX_PATH)
    with open(DOCS_PATH, "w") as f:
        json.dump({}, f)

    return jsonify({"status": "success"})

# -------------------------
# Status
# -------------------------

@app.route("/status", methods=["GET"])
def status():
    return jsonify({
        "status": "running",
        "dimension": DIMENSION,
        "total_vectors": index.ntotal
    })

# -------------------------
# Entrypoint
# -------------------------

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
