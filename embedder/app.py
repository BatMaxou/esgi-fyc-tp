import os
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
from tika import parser
from langchain_text_splitters import CharacterTextSplitter

app = FastAPI(title="Embedder API")
model = SentenceTransformer("all-MiniLM-L6-v2")

class TextRequest(BaseModel):
    text: str

class DocumentRequest(BaseModel):
    path: str

@app.post("/")
async def embed(request: TextRequest):
    if not request.text:
        raise HTTPException(status_code=400, detail="Missing text")

    vector = model.encode(request.text).tolist()

    return vector

@app.post("/document")
async def embed_document(request: DocumentRequest):
    if not request.path:
        raise HTTPException(status_code=400, detail="Missing document")

    document_text = parser.from_file(
        os.environ['PHP_URL'] + request.path,
        serverEndpoint=os.environ['TIKA_URL']
    )
    
    if not document_text or 'content' not in document_text:
        raise HTTPException(status_code=400, detail="Could not parse document")

    text_splitter = CharacterTextSplitter(chunk_size=500, chunk_overlap=100)
    chunks = text_splitter.create_documents([document_text['content']])

    vectors = {}
    for chunk in chunks:
        content = chunk.page_content
        vector = model.encode(content).tolist()
        vectors[content] = vector

    return vectors
