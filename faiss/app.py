import os
import numpy as np
import faiss
from flask import Flask, request, jsonify

app = Flask(__name__)

# Configuration
INDEX_PATH = os.environ.get('INDEX_PATH', '/data/faiss/index.idx')
DIMENSION = int(os.environ.get('VECTOR_DIMENSION', '384'))
INDEX_TYPE = os.environ.get('INDEX_TYPE', 'Flat')

# Create or load index
if os.path.exists(INDEX_PATH):
    index = faiss.read_index(INDEX_PATH)
    print(f"Loaded index from {INDEX_PATH}, size: {index.ntotal}")
else:
    if INDEX_TYPE == 'Flat':
        index = faiss.IndexFlatL2(DIMENSION)
    elif INDEX_TYPE == 'IVF':
        quantizer = faiss.IndexFlatL2(DIMENSION)
        index = faiss.IndexIVFFlat(quantizer, DIMENSION, 100)
        # Need to train with some vectors before using IVF
    else:
        # Default to flat index
        index = faiss.IndexFlatL2(DIMENSION)
    
    print(f"Created new {INDEX_TYPE} index with dimension {DIMENSION}")

@app.route('/add', methods=['POST'])
def add_vectors():
    data = request.json
    vectors = np.array(data['vectors'], dtype=np.float32)
    ids = None
    if 'ids' in data:
        ids = np.array(data['ids'], dtype=np.int64)
    
    if ids is not None:
        index.add_with_ids(vectors, ids)
    else:
        index.add(vectors)
    
    # Save index after update
    faiss.write_index(index, INDEX_PATH)
    return jsonify({"status": "success", "vectors_added": len(vectors)})

@app.route('/search', methods=['POST'])
def search_vectors():
    data = request.json
    query_vectors = np.array(data['query_vectors'], dtype=np.float32)
    k = data.get('k', 5)
    
    distances, indices = index.search(query_vectors, k)
    
    results = []
    for i in range(len(query_vectors)):
        results.append({
            "distances": distances[i].tolist(),
            "indices": indices[i].tolist()
        })
    
    return jsonify({"status": "success", "results": results})

@app.route('/status', methods=['GET'])
def status():
    return jsonify({
        "status": "running",
        "index_type": INDEX_TYPE,
        "dimension": DIMENSION,
        "total_vectors": index.ntotal
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
