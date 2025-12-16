FROM python:3.13.10-slim

WORKDIR /app

COPY . .

RUN pip install --upgrade pip
# Tika requires Java
RUN apt-get update && \
  apt-get install -y --no-install-recommends \
      openjdk-21-jre-headless \
  && rm -rf /var/lib/apt/lists/*

# Only user CPU versions to gain smaller image size
RUN pip install --no-cache-dir torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cpu

RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 5000

CMD ["python", "app.py"]
