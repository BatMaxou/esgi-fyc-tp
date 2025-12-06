from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer

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

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)

