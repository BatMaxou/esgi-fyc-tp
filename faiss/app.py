import os
import json
import threading
import numpy as np
import faiss
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any

# ========================
# Configuration
# ========================
INDEX_PATH = "/data/faiss/index.idx"
DOCS_PATH = "/data/faiss/documents.json"
DIMENSION = # -- Number of dimensions --

# ========================
# FAISS setup
# ========================
faiss.omp_set_num_threads(os.cpu_count())
if os.path.exists(INDEX_PATH):
    index = faiss.read_index(INDEX_PATH)
else:
    base_index = faiss.IndexFlatL2(DIMENSION)
    index = faiss.IndexIDMap(base_index)

app = FastAPI(title="FAISS Vector Store API")

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
# Pydantic Models
# ========================
class AddVectorsRequest(BaseModel):
    ids: List[int]
    vectors: List[List[float]]
    documents: List[Any]

class SearchRequest(BaseModel):
    query_vectors: List[List[float]] | List[float]
    limit: int = Field(default=5, ge=1)

class SearchResult(BaseModel):
    id: int
    distance: float
    document: Optional[Any]

class SearchResponse(BaseModel):
    results: List[List[SearchResult]]

class StatusResponse(BaseModel):
    status: str
    dimension: int
    total_vectors: int

class SuccessResponse(BaseModel):
    status: str
    total_vectors: Optional[int] = None

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
@app.post("/init", response_model=SuccessResponse)
async def init_index():
    global index, docs
    base_index = faiss.IndexFlatL2(DIMENSION)
    index = faiss.IndexIDMap(base_index)
    docs = {}
    faiss.write_index(index, INDEX_PATH)
    with open(DOCS_PATH, "w") as f:
        json.dump({}, f)

    return {"status": "success"}

# -------------------------
# Add vectors
# -------------------------
@app.post("/add", response_model=SuccessResponse)
async def add_vectors(request: AddVectorsRequest):
    if not request.ids or not request.vectors or not request.documents:
        raise HTTPException(status_code=400, detail="Invalid payload")

    if len(request.ids) != len(request.vectors) or len(request.ids) != len(request.documents):
        raise HTTPException(status_code=400, detail="Mismatched lengths of ids, vectors, and documents")

    vectors_array = np.asarray(request.vectors, dtype=np.float32)
    ids_array = np.asarray(request.ids, dtype=np.int64)

    index.add_with_ids(vectors_array, ids_array)

    with docs_lock:
        for i, doc_id in enumerate(ids_array):
            docs[str(int(doc_id))] = request.documents[i]

    threading.Thread(target=persist_index_async, daemon=True).start()
    threading.Thread(target=persist_docs_async, daemon=True).start()

    return {"status": "success", "total_vectors": index.ntotal}

# -------------------------
# Search
# -------------------------
@app.post("/search", response_model=SearchResponse)
async def search_vectors(request: SearchRequest):
    query_vectors = np.asarray(request.query_vectors, dtype=np.float32)

    if query_vectors.ndim == 1:
        query_vectors = query_vectors.reshape(1, -1)

    distances, ids = index.search(query_vectors, request.limit)

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

    return {"results": results}

# -------------------------
# Delete all
# -------------------------
@app.delete("/delete", response_model=SuccessResponse)
async def delete_all_vectors():
    index.reset()

    with docs_lock:
        docs.clear()

    faiss.write_index(index, INDEX_PATH)
    with open(DOCS_PATH, "w") as f:
        json.dump({}, f)

    return {"status": "success"}

# -------------------------
# Status
# -------------------------
@app.get("/status", response_model=StatusResponse)
async def status():
    return {
        "status": "running",
        "dimension": DIMENSION,
        "total_vectors": index.ntotal
    }
