#!/bin/bash
# exit on error
set -e

ollama serve &
ollama_pid=$!

sleep 5

if ! ollama list | grep -q "llama3.2"; then
  ollama pull llama3.2
fi

wait $ollama_pid

