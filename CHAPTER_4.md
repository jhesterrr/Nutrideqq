# CHAPTER 4

## System Implementation

### 4.1 Introduction

This chapter presents the implementation of the NutriDeq system, a web-based nutrition and health management platform designed to support dietitians, staff, and clients in monitoring nutritional status, tracking health progress, and making informed dietary decisions. The system was developed as a capstone project to address the need for a centralized and efficient nutrition management solution that integrates health tracking, food database management, client records, and decision-support tools into one platform.

The implementation phase focused on transforming the system requirements into a functional application that is easy to use, secure, and capable of supporting different user roles. The system is designed for three primary user groups: administrators, staff/dietitians, and regular users/clients. Each group has access to role-based features and dashboards based on their responsibilities and access level.

### 4.2 System Architecture

The NutriDeq system follows a three-layer architecture consisting of the presentation layer, application logic layer, and data layer. The presentation layer is composed of HTML, CSS, JavaScript, and PHP-generated pages that provide the system interface. The application logic layer handles authentication, session management, role-based access control, calculations, and processing of user requests. The data layer uses a MySQL database to store user profiles, health data, nutrient records, dietary entries, and administrative information.

Figure 4.1 illustrates the overall architecture of the system:

- Client devices access the system through a web browser.
- PHP scripts process the requests and handle the business rules.
- The database stores persistent system data.
- Role-based navigation and modules determine which interface and features are available to each user.
- API endpoints and AJAX handlers support data retrieval and processing in real time.

The architecture enables modularity and scalability, allowing future enhancements such as additional AI-driven recommendations, expanded food data, and integration with external health monitoring systems.

### 4.3 Development Tools and Technologies

The implementation of the project uses a combination of modern and practical web technologies suitable for a capstone system.

#### 4.3.1 Front-end Technologies

The front end of the system uses HTML, CSS, and JavaScript to create an intuitive and responsive user interface. Custom styling is applied to produce a professional healthcare dashboard with visually organized modules such as cards, charts, navigation bars, and summary panels. JavaScript is used for interactivity, dynamic calculations, responsiveness, and UI enhancements.

The system also uses Chart.js for dashboard analytics and data visualization. This allows users to view metrics such as calorie intake, BMI changes, nutrient summaries, and other health indicators in a more understandable format.

#### 4.3.2 Back-end Technologies

The server-side implementation is built with PHP, which is responsible for handling user authentication, session management, access control, business logic, and database interactions. PHP scripts are structured modularly so that each page and module can be maintained independently. Sessions are used to track active users and enforce secure access controls for each user role.

The project also includes API endpoints and AJAX handlers that process asynchronous requests. These endpoints are used for functions such as saving diary entries, calculating nutrient totals, retrieving personalized recommendations, and communicating with AI-based or rule-based analysis modules.

#### 4.3.3 Database Technology

The system uses MySQL as its main database management system. MySQL is chosen because it is reliable, efficient, and compatible with PHP-based web applications. The database stores major entities such as users, staff, clients, health records, dietary logs, food items, and nutrient standards.

A structured database design supports retrieval and updating of records while maintaining integrity and consistency. This is important in a nutrition system because health data and personalized recommendations must be accurate and traceable.

### 4.4 User Roles and Access Control

One of the most important parts of the system is its role-based design. The system distinguishes among three primary roles:

1. Administrator
2. Staff/Dietitian
3. Client/User

Each role has a different dashboard, navigation menu, and set of permissions. Administrators manage user accounts, staff records, and client information. Staff users are responsible for monitoring client health, dietary progress, and documentation. Client users can log their health information, food intake, and progress while also accessing their personal dashboard and recommendations.

This role-based structure improves security and ensures that users only see relevant records and functions. For example, a client cannot access administrative tools intended for management staff, while staff can review relevant client information without modifying system-wide records.

### 4.5 Main Functional Modules

The implementation includes several functional modules that work together to support the overall objective of the system.

#### 4.5.1 User and Account Management

The user management module is responsible for creating, updating, and managing user accounts. This includes login authentication, session validation, role assignment, and user status tracking. The login and logout flow is controlled through secure PHP session handling to prevent unauthorized access.

User information such as name, email, role, and account status are stored in the database, allowing the system to dynamically render role-specific interfaces and permissions.

#### 4.5.2 Dashboard Module

The dashboard is the primary interface for each user after login. It provides a centralized summary of relevant information. For example, administrative users can view system-wide statistics and management controls, while client users are presented with personal health summaries, diet tracking progress, and recommendation panels.

The dashboard consolidates multiple views into a single entry point, allowing users to quickly understand their current status and the next action needed.

#### 4.5.3 Health and Anthropometric Tracking

The system includes a health tracking module where users can input or review anthropometric data such as age, height, weight, BMI, and other health indicators. This is essential in nutrition care because accurate health data forms the basis for individualized nutritional recommendations.

The records are used to generate health summaries and support calculations for dietary adjustments. These inputs help dietitians and clients monitor their physical changes over time and evaluate whether the nutrition plan is producing the desired results.

#### 4.5.4 Food Diary and Nutrient Tracking

Food diary management is one of the core features of the system. Users can log the foods they consume, and the system compares these entries to nutrition goals and recommended intake levels. This supports better dietary self-monitoring and allows dietitians to review eating patterns and identify deficiencies or overconsumption.

The nutrient tracking component calculates daily totals for calories, protein, carbohydrates, fats, and other essential nutrients. These values are compared with health goals and standards to provide a clearer picture of whether the user’s diet is aligned with their nutritional needs.

#### 4.5.5 Food Exchange and FCT Library

The system also includes food exchange and food composition table (FCT) modules. The food exchange feature organizes foods into exchange groups and helps users maintain balanced dietary patterns. The FCT library acts as a reference for nutritional values associated with common foods, making it easier to estimate intake and plan meals.

These modules are particularly useful for dietitians and clients who need an evidence-based and practical way to choose appropriate foods while staying within target nutrient ranges.

#### 4.5.6 Nutrition Calculator and Recommendations

The nutrition calculator helps to compute recommended caloric needs and weight-related targets. It supports planning based on user-specific metrics and health goals. This feature allows the platform to provide better guidance and encourage healthier behavior based on each user's individual condition.

The recommendation engine further enhances the system by generating suggestions based on a user’s intake, nutrient status, and health condition. These recommendations may include adjustments in meal composition, nutrient balance, and lifestyle guidance.

#### 4.5.7 Messaging and Communication Module

The system includes a messaging feature that allows communication between users and staff or administrators. This helps support collaboration in nutrition care by allowing updates, clarification, and follow-up messages to be exchanged within the platform. This is especially useful in cases where the dietitian needs to provide feedback or monitor progress over time.

#### 4.5.8 AI and Decision Support

The project also integrates AI-assisted analysis for nutritional recommendation and symptom-to-nutrient interpretation. This module processes user input and uses structured rules or AI-based logic to generate insights related to dietary needs and potential nutrient deficiencies. This type of intelligent support helps the platform go beyond static record management by offering more personalized guidance.

### 4.6 Implementation of the Database Structure

The system database is organized around the core requirements of the application. Tables are designed to store essential data such as users, roles, client profiles, nutrition data, food entries, staff assignments, messages, and logs. The implementation relies on relational database design to maintain consistency and support efficient queries.

Some important database functions include:

- storing user authentication and account details;
- capturing client and anthropometric information;
- recording food diary entries and nutrient totals;
- storing staff-to-client assignments;
- preserving communication records; and
- maintaining nutrition-related references and standards.

By structuring the system data in a normalized form, the application improves organization, reduces duplication, and supports easier future extension.

### 4.7 Security and Session Management

Security is a critical component of the implementation. The system protects pages using session checks and role-based access validation. Before accessing restricted modules, the application verifies whether the user is logged in and whether the user role has permission to access the current page.

This design prevents unauthorized navigation and protects sensitive healthcare data. The system also uses PHP sessions to maintain user state and manage login sessions throughout the application.

### 4.8 User Interface and Experience

The system is designed to be user-friendly and visually organized. The interface uses clear labels, structured layout sections, summary cards, and responsive design to ensure that users can easily navigate critical functions. A professional healthcare aesthetic is applied to build trust and improve usability, especially in a nutrition and wellness context.

For users with different roles, the interface adjusts based on specific needs. Admin dashboards emphasize management and monitoring tools, while client dashboards emphasize personal metrics, food intake tracking, and goals. This personalization helps improve overall user engagement and reduces information overload.

### 4.9 System Workflow

The implementation process follows a practical workflow that begins with user registration and ends with data analysis and recommendation delivery. A typical workflow is as follows:

1. User logs into the system.
2. The system validates credentials and loads the appropriate dashboard.
3. The user enters health or food-related data.
4. The application processes the data through calculation and validation logic.
5. The system stores the data in the database.
6. The dashboard updates with new metrics and summaries.
7. The user or staff reviews insights and recommendations.
8. Further actions such as follow-up communication or plan adjustments can be made.

This workflow demonstrates how the system supports continuous monitoring and informed decision-making in nutrition care.

### 4.10 Summary

The implementation of the NutriDeq system demonstrates the successful development of a functional nutrition management web application. It integrates essential modules such as user management, health tracking, dietary records, nutrition calculations, food exchange tools, client communication, and recommendation support into a unified platform. The project uses a practical and scalable architecture involving PHP, MySQL, HTML, CSS, JavaScript, and API-driven processing.

The system addresses the needs of administrators, staff, and clients by providing role-based access, dynamic dashboards, and data-driven recommendations. Through careful planning, modular development, and focus on usability, the platform reflects a complete capstone project implementation that is relevant to real-world nutrition care and health management.

---

Prepared for documentation use in the NutriDeq capstone project.
