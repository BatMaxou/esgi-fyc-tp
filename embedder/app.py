import os
from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
from tika import parser
from langchain_text_splitters import CharacterTextSplitter

app = Flask(__name__)
model = SentenceTransformer("all-MiniLM-L6-v2")

@app.route("/", methods=["POST"])
def embed():
    data = request.json
    text = data.get("text")
    if not text:
        return jsonify({"error": "Missing text"}), 400

    vector = model.encode(text).tolist()

    return jsonify(vector)

@app.route("/document", methods=["POST"])
def embed_document():
    data = request.json
    path = data.get("path")
    if not path:
        return jsonify({"error": "Missing document"}), 400

    document_text = parser.from_file(
        os.environ['PHP_URL'] + path,
        serverEndpoint=os.environ['TIKA_URL']
    )
    if not document_text or 'content' not in document_text:
        return jsonify({"error": "Could not parse document"}), 400

    text_splitter = CharacterTextSplitter(chunk_size=500, chunk_overlap=100)
    chunks = text_splitter.create_documents([document_text['content']])

    vectors = {}
    for chunk in chunks:
        content = chunk.page_content
        vector = model.encode(content).tolist()
        vectors[content] = vector

    return jsonify(vectors)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)

