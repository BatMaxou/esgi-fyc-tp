FROM python:3.13.10-slim

WORKDIR /app

COPY . .

RUN pip install --upgrade pip
RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 5000

CMD ["fastapi", "run", "app.py", "--port", "5000"]
