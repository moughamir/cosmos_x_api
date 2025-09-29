CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    handle TEXT NOT NULL UNIQUE,
    title TEXT,
    body_html TEXT,
    published_at TEXT,
    created_at TEXT,
    updated_at TEXT,
    vendor TEXT,
    product_type TEXT,
    tags TEXT,
    price REAL,
    compare_at_price REAL,
    in_stock BOOLEAN,
    rating REAL,
    review_count INTEGER,
    bestseller_score REAL,
    raw_json TEXT
);
CREATE TABLE product_images (
    id INTEGER PRIMARY KEY,
    product_id INTEGER,
    position INTEGER,
    src TEXT,
    width INTEGER,
    height INTEGER,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE TABLE product_variants (
    id INTEGER PRIMARY KEY,
    product_id INTEGER,
    title TEXT,
    sku TEXT,
    price REAL,
    compare_at_price REAL,
    available BOOLEAN,
    grams INTEGER,
    position INTEGER,
    option1 TEXT,
    option2 TEXT,
    option3 TEXT,
    created_at TEXT,
    updated_at TEXT,
    FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE TABLE product_options (
    id INTEGER PRIMARY KEY,
    product_id INTEGER,
    name TEXT,
    position INTEGER,
    "values" TEXT,
    FOREIGN KEY(product_id) REFERENCES products(id)
);
CREATE VIRTUAL TABLE products_fts USING fts5(
    title,
    body_html,
    vendor,
    product_type,
    content = 'products'
)
/* products_fts(title,body_html,vendor,product_type) */
;
CREATE TABLE IF NOT EXISTS 'products_fts_data'(id INTEGER PRIMARY KEY, block BLOB);
CREATE TABLE IF NOT EXISTS 'products_fts_idx'(segid, term, pgno, PRIMARY KEY(segid, term)) WITHOUT ROWID;
CREATE TABLE IF NOT EXISTS 'products_fts_docsize'(id INTEGER PRIMARY KEY, sz BLOB);
CREATE TABLE IF NOT EXISTS 'products_fts_config'(k PRIMARY KEY, v) WITHOUT ROWID;
CREATE INDEX idx_images_product_id ON product_images (product_id);
CREATE INDEX idx_variants_product_id ON product_variants (product_id);
CREATE INDEX idx_options_product_id ON product_options (product_id);
CREATE INDEX idx_products_handle ON products (handle);
CREATE INDEX idx_products_bestseller_score ON products (bestseller_score DESC);