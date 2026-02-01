import sys
import pandas as pd
import mysql.connector
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

# ---------------- GET USER ID ----------------
if len(sys.argv) < 2:
    print("❌ No user_id passed")
    sys.exit()

target_user_id = int(sys.argv[1])

# ---------------- DB CONNECTION ----------------
db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="education"
)
cursor = db.cursor(dictionary=True)

# ---------------- LOAD DATA ----------------
cursor.execute("SELECT id, title, description FROM courses")
courses = pd.DataFrame(cursor.fetchall())

cursor.execute("SELECT student_id AS user_id, course_id FROM student_courses WHERE status='approved'")
purchases = pd.DataFrame(cursor.fetchall())

if courses.empty:
    print("❌ No courses found.")
    sys.exit()

# ---------------- CONTENT BASED ----------------
tfidf = TfidfVectorizer(stop_words="english")
tfidf_matrix = tfidf.fit_transform(courses['description'].fillna(""))

cosine_sim = cosine_similarity(tfidf_matrix, tfidf_matrix)

def recommend_by_content(course_id, top_n=3):  # 👈 top_n=3 now
    try:
        idx = courses[courses['id'] == course_id].index[0]
    except IndexError:
        return pd.DataFrame()
    sim_scores = list(enumerate(cosine_sim[idx]))
    sim_scores = sorted(sim_scores, key=lambda x: x[1], reverse=True)
    sim_scores = sim_scores[1:top_n+1]
    indices = [i[0] for i in sim_scores]
    return courses.iloc[indices][['id', 'title']]

# ---------------- COLLABORATIVE ----------------
if not purchases.empty:
    user_course_matrix = purchases.pivot_table(index='user_id', columns='course_id', aggfunc=len, fill_value=0)
    if len(user_course_matrix.columns) > 1:
        collab_sim = cosine_similarity(user_course_matrix.T)
        collab_sim_df = pd.DataFrame(collab_sim, index=user_course_matrix.columns, columns=user_course_matrix.columns)
    else:
        collab_sim_df = pd.DataFrame()
else:
    collab_sim_df = pd.DataFrame()

def recommend_by_collab(course_id, top_n=3):  # 👈 top_n=3 now
    if collab_sim_df.empty or course_id not in collab_sim_df.columns:
        return pd.DataFrame()
    similar_courses = collab_sim_df[course_id].sort_values(ascending=False)[1:top_n+1]
    return courses[courses['id'].isin(similar_courses.index)][['id','title']]

# ---------------- HYBRID ----------------
def recommend_hybrid(course_id, top_n=3):  # 👈 top_n=3 now
    content_recs = recommend_by_content(course_id, top_n)
    collab_recs = recommend_by_collab(course_id, top_n)
    combined = pd.concat([content_recs, collab_recs]).drop_duplicates().head(top_n)  # 👈 enforce 3
    return combined

# ---------------- POPULAR FALLBACK ----------------
def recommend_popular(top_n=3):  # 👈 top_n=3 now
    if purchases.empty:
        return courses.sample(min(top_n, len(courses)))
    pop = purchases['course_id'].value_counts().head(top_n).index
    return courses[courses['id'].isin(pop)][['id','title']]

# ---------------- GENERATE FOR THIS USER ----------------
cursor.execute("DELETE FROM recommendations WHERE user_id=%s", (target_user_id,))

user_purchases = purchases[purchases['user_id'] == target_user_id]['course_id'].tolist()
recommended = pd.DataFrame()

if user_purchases:
    for course in user_purchases:
        recs = recommend_hybrid(course, 3)  # 👈 top_n=3
        recommended = pd.concat([recommended, recs])
    recommended = recommended.drop_duplicates().head(3)  # 👈 final limit to 3
    if recommended.empty:
        recommended = recommend_popular(3)
else:
    recommended = recommend_popular(3)

for _, row in recommended.iterrows():
    cursor.execute(
        "INSERT INTO recommendations (user_id, course_id) VALUES (%s, %s)",
        (int(target_user_id), int(row['id']))
    )

db.commit()
cursor.close()
db.close()

print(f"✅ Recommendations updated for user {target_user_id} (max 3)")
